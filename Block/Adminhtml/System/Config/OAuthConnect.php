<?php

declare(strict_types=1);

namespace SimPay\Magento\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use SimPay\Magento\Model\Config;
use SimPay\Magento\Service\OAuth\OAuthService;
use SimPay\SDK\OAuth\PendingConnection;

/**
 * "Connect with SimPay" panel: connection status, connect/disconnect buttons and the service picker
 * shown after returning from SimPay.
 */
class OAuthConnect extends Field
{
    protected $_template = 'SimPay_Magento::system/config/oauth_connect.phtml';

    private ?AbstractElement $element = null;

    public function __construct(
        Context $context,
        private readonly OAuthService $oauthService,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function render(AbstractElement $element): string
    {
        // not a real config value - no "Use Default" checkbox / scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $this->element = $element;

        return $this->_toHtml();
    }

    /**
     * HTML id of the "Connection method" select, used to switch to the OAuth panel when the picker is pending.
     */
    public function getAuthMethodHtmlId(): string
    {
        $id = (string) $this->element?->getHtmlId();

        return (string) preg_replace('/oauth_connect$/', 'auth_method', $id);
    }

    /**
     * @return array{scope: string, scope_id: int}
     */
    public function getScope(): array
    {
        return $this->oauthService->resolveScope(
            $this->getRequest()->getParam('website'),
            $this->getRequest()->getParam('store')
        );
    }

    /**
     * @return array<string, int>
     */
    private function getScopeParams(): array
    {
        $params = $this->oauthService->getConfigPageParams($this->getScope());
        unset($params['section']);

        return $params;
    }

    public function isConnected(): bool
    {
        return $this->oauthService->isConnected($this->getScope());
    }

    public function getAccountEmail(): string
    {
        return $this->oauthService->getValue(Config::XML_PATH_OAUTH_ACCOUNT_EMAIL, $this->getScope());
    }

    public function getServiceId(): string
    {
        return $this->oauthService->getValue(Config::XML_PATH_SERVICE_ID, $this->getScope());
    }

    public function getServiceName(): string
    {
        return $this->oauthService->getValue(Config::XML_PATH_OAUTH_SERVICE_NAME, $this->getScope());
    }

    public function isHttpsOk(): bool
    {
        return $this->oauthService->isRedirectUriAllowed();
    }

    public function getRedirectUri(): string
    {
        return (string) strtok($this->oauthService->getRedirectUri(), '?');
    }

    public function getPendingConnection(): ?PendingConnection
    {
        return $this->oauthService->getConnector()->getPendingConnection();
    }

    public function getSuggestedServiceId(PendingConnection $pending): ?string
    {
        return $pending->suggestServiceId($this->oauthService->getShopUrl($this->oauthService->getRememberedScope()));
    }

    public function getConnectUrl(): string
    {
        return $this->getUrl('simpay/oauth/connect', $this->getScopeParams());
    }

    public function getDisconnectUrl(): string
    {
        return $this->getUrl('simpay/oauth/disconnect');
    }

    /**
     * @return array<string, int>
     */
    public function getDisconnectParams(): array
    {
        return $this->getScopeParams();
    }

    public function getProvisionUrl(): string
    {
        return $this->getUrl('simpay/oauth/provision');
    }

    public function getCancelUrl(): string
    {
        return $this->getUrl('simpay/oauth/cancel');
    }
}
