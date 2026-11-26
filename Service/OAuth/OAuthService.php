<?php

declare(strict_types=1);

namespace SimPay\Magento\Service\OAuth;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Phrase;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use SimPay\Magento\Model\Config;
use SimPay\Magento\OAuth\SessionOAuthStorage;
use SimPay\SDK\Exception\ApiException;
use SimPay\SDK\OAuth\ConnectionResult;
use SimPay\SDK\OAuth\OAuthClient;
use SimPay\SDK\OAuth\OAuthConnector;
use SimPay\SDK\OAuth\OAuthException;

/**
 * "Connect with SimPay" glue. The flow itself lives in simpay/ecommerce (OAuthConnector);
 * this service only wires it to Magento: admin session, URLs, configuration scopes and messages.
 *
 * A scope is represented as ['scope' => 'default'|'websites'|'stores', 'scope_id' => int].
 */
class OAuthService
{
    /** OAuth client registered by SimPay for the Magento plugin (public client, PKCE). */
    public const OAUTH_CLIENT_ID = '01a1e47b-b60d-72db-a2cb-2477a7ff5702';

    /** Configuration scope the flow was started from - credentials are saved there. */
    private const SCOPE_SESSION_KEY = 'simpay_oauth_scope';

    /** SimPay limit for API key names. */
    private const API_KEY_NAME_MAX_LENGTH = 32;

    private const CREDENTIAL_PATHS = [
        Config::XML_PATH_API_PASSWORD,
        Config::XML_PATH_SERVICE_ID,
        Config::XML_PATH_IPN_SIGNATURE_KEY,
        Config::XML_PATH_OAUTH_ACCOUNT_EMAIL,
        Config::XML_PATH_OAUTH_SERVICE_NAME,
    ];

    private ?OAuthConnector $connector = null;

    public function __construct(
        private readonly SessionOAuthStorage $storage,
        private readonly BackendUrlInterface $backendUrl,
        private readonly HttpRequest $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    public function getConnector(): OAuthConnector
    {
        return $this->connector ??= OAuthConnector::create(self::OAUTH_CLIENT_ID, $this->storage);
    }

    /**
     * Absolute callback URL registered for the flow (no secret key - SimPay redirects the browser back to it).
     *
     * Behind a TLS-terminating proxy the configured admin URL may still be http:// while the browser uses https;
     * SimPay accepts https only, so the scheme follows the actual request in that case.
     */
    public function getRedirectUri(): string
    {
        $url = (string) $this->backendUrl->getUrl('simpay/oauth/callback', ['_nosecret' => true, '_nosid' => true]);

        if ($this->request->isSecure()) {
            $url = (string) preg_replace('#^http://#i', 'https://', $url);
        }

        return $url;
    }

    public function isRedirectUriAllowed(): bool
    {
        return OAuthClient::isRedirectUriAllowed($this->getRedirectUri());
    }

    /**
     * Scope of the configuration page, from its "website" / "store" request parameters.
     *
     * @return array{scope: string, scope_id: int}
     */
    public function resolveScope(mixed $websiteId, mixed $storeId): array
    {
        if (is_numeric($storeId) && (int) $storeId > 0) {
            return ['scope' => ScopeInterface::SCOPE_STORES, 'scope_id' => (int) $storeId];
        }

        if (is_numeric($websiteId) && (int) $websiteId > 0) {
            return ['scope' => ScopeInterface::SCOPE_WEBSITES, 'scope_id' => (int) $websiteId];
        }

        return ['scope' => ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 'scope_id' => 0];
    }

    /**
     * @param array{scope: string, scope_id: int} $scope
     */
    public function rememberScope(array $scope): void
    {
        $this->storage->set(self::SCOPE_SESSION_KEY, $scope);
    }

    /**
     * @return array{scope: string, scope_id: int}
     */
    public function getRememberedScope(): array
    {
        $scope = $this->storage->get(self::SCOPE_SESSION_KEY) ?? [];

        return match ($scope['scope'] ?? null) {
            ScopeInterface::SCOPE_STORES => $this->resolveScope(null, $scope['scope_id'] ?? 0),
            ScopeInterface::SCOPE_WEBSITES => $this->resolveScope($scope['scope_id'] ?? 0, null),
            default => $this->resolveScope(null, null),
        };
    }

    /**
     * Route params of the payment methods configuration page for the given scope.
     *
     * @param array{scope: string, scope_id: int} $scope
     * @return array<string, int|string>
     */
    public function getConfigPageParams(array $scope): array
    {
        $params = ['section' => 'payment'];

        if ($scope['scope'] === ScopeInterface::SCOPE_STORES) {
            $params['store'] = $scope['scope_id'];
        } elseif ($scope['scope'] === ScopeInterface::SCOPE_WEBSITES) {
            $params['website'] = $scope['scope_id'];
        }

        return $params;
    }

    /**
     * @param array{scope: string, scope_id: int} $scope
     */
    public function getValue(string $path, array $scope): string
    {
        if ($scope['scope'] === ScopeConfigInterface::SCOPE_TYPE_DEFAULT) {
            return (string) $this->scopeConfig->getValue($path);
        }

        return (string) $this->scopeConfig->getValue($path, $scope['scope'], $scope['scope_id']);
    }

    /**
     * @param array{scope: string, scope_id: int} $scope
     */
    public function isConnected(array $scope): bool
    {
        return $this->getValue(Config::XML_PATH_AUTH_METHOD, $scope) === Config::AUTH_METHOD_OAUTH
            && $this->getValue(Config::XML_PATH_API_PASSWORD, $scope) !== '';
    }

    /**
     * IPN URL of the storefront the scope belongs to.
     *
     * @param array{scope: string, scope_id: int} $scope
     */
    public function getIpnUrl(array $scope): string
    {
        return $this->getShopUrl($scope) . '/simpay/ipn/index';
    }

    /**
     * @param array{scope: string, scope_id: int} $scope
     */
    public function getShopUrl(array $scope): string
    {
        $store = $this->getStoreForScope($scope);

        return rtrim((string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, true), '/');
    }

    /**
     * Name of the API key shown in the SimPay panel, e.g. "Magento shop.pl".
     *
     * @param array{scope: string, scope_id: int} $scope
     */
    public function getApiKeyName(array $scope): string
    {
        $host = (string) parse_url($this->getShopUrl($scope), PHP_URL_HOST);

        return mb_substr('Magento ' . $host, 0, self::API_KEY_NAME_MAX_LENGTH);
    }

    /**
     * @param array{scope: string, scope_id: int} $scope
     */
    public function saveConnection(ConnectionResult $result, array $scope): void
    {
        $values = [
            Config::XML_PATH_API_PASSWORD => $result->apiKey,
            Config::XML_PATH_SERVICE_ID => $result->serviceId,
            Config::XML_PATH_IPN_SIGNATURE_KEY => $result->ipnSignatureKey,
            Config::XML_PATH_OAUTH_ACCOUNT_EMAIL => $result->accountEmail,
            Config::XML_PATH_OAUTH_SERVICE_NAME => $result->serviceName,
            Config::XML_PATH_AUTH_METHOD => Config::AUTH_METHOD_OAUTH,
        ];

        foreach ($values as $path => $value) {
            $this->configWriter->save($path, $value, $scope['scope'], $scope['scope_id']);
        }

        $this->cacheTypeList->cleanType('config');
    }

    /**
     * Removes OAuth-provisioned credentials from the scope (a website/store scope falls back to its parent).
     * The API has no endpoint for revoking access, so the API key stays in the SimPay panel.
     *
     * @param array{scope: string, scope_id: int} $scope
     */
    public function clearConnection(array $scope): void
    {
        foreach (self::CREDENTIAL_PATHS as $path) {
            $this->configWriter->delete($path, $scope['scope'], $scope['scope_id']);
        }

        $this->cacheTypeList->cleanType('config');
    }

    /**
     * Translated message for the merchant.
     */
    public function getErrorMessage(\Throwable $e): Phrase
    {
        if ($e instanceof OAuthException) {
            return match ($e->getReason()) {
                OAuthException::REDIRECT_URI_NOT_ALLOWED => __('Connecting via SimPay requires the Magento admin panel to be served over HTTPS. Enter the credentials manually instead.'),
                OAuthException::ACCESS_DENIED => __('Connection with SimPay was cancelled.'),
                OAuthException::AUTHORIZATION_FAILED => __('SimPay authorization failed: %1', (string) $e->getDetails()),
                OAuthException::INVALID_STATE => __('Invalid or expired SimPay authorization. Please try connecting again.'),
                OAuthException::NO_PAYMENT_SERVICES => __('No active payment services were found on your SimPay account. Create a payment service in the SimPay panel first.'),
                OAuthException::EXPIRED => __('The SimPay authorization has expired. Please connect again.'),
                OAuthException::UNKNOWN_SERVICE => __('Please select a payment service.'),
                OAuthException::UNSUPPORTED_IPN_VERSION => __('The selected service uses IPN version %1, but this module supports IPN 2.0 only. Change the IPN version to 2.0 in the service settings in the SimPay panel, then select the service again. Nothing was changed.', (string) $e->getDetails()),
                default => __('Could not connect to SimPay: %1', $e->getMessage()),
            };
        }

        $message = $e instanceof ApiException && $e->getApiMessage() ? $e->getApiMessage() : $e->getMessage();

        return __('Could not connect to SimPay: %1', $message);
    }

    /**
     * @param array{scope: string, scope_id: int} $scope
     */
    private function getStoreForScope(array $scope): StoreInterface
    {
        if ($scope['scope'] === ScopeInterface::SCOPE_STORES) {
            return $this->storeManager->getStore($scope['scope_id']);
        }

        if ($scope['scope'] === ScopeInterface::SCOPE_WEBSITES) {
            $store = $this->storeManager->getWebsite($scope['scope_id'])->getDefaultStore();
            if ($store instanceof Store) {
                return $store;
            }
        }

        return $this->storeManager->getDefaultStoreView() ?? $this->storeManager->getStore();
    }
}
