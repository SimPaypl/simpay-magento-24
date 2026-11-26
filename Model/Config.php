<?php

declare(strict_types=1);

namespace SimPay\Magento\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use SimPay\Magento\Model\Ui\ConfigProvider;

class Config
{
    public const SUPPORTED_CURRENCY = 'PLN';

    public const XML_PATH_SERVICE_ID = 'payment/' . ConfigProvider::CODE . '/service_id';
    public const XML_PATH_API_PASSWORD = 'payment/' . ConfigProvider::CODE . '/api_password';
    public const XML_PATH_IPN_SIGNATURE_KEY = 'payment/' . ConfigProvider::CODE . '/ipn_signature_key';
    public const XML_PATH_AUTH_METHOD = 'payment/' . ConfigProvider::CODE . '/auth_method';
    public const XML_PATH_OAUTH_ACCOUNT_EMAIL = 'payment/' . ConfigProvider::CODE . '/oauth_account_email';
    public const XML_PATH_OAUTH_SERVICE_NAME = 'payment/' . ConfigProvider::CODE . '/oauth_service_name';

    public const AUTH_METHOD_OAUTH = 'oauth';
    public const AUTH_METHOD_MANUAL = 'manual';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getServiceId(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'payment/' . ConfigProvider::CODE . '/service_id',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getBearerToken(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'payment/' . ConfigProvider::CODE . '/api_password',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getIpnSignatureKey(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(
            'payment/' . ConfigProvider::CODE . '/ipn_signature_key',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isIpnCheckIpEnabled(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(
            'payment/' . ConfigProvider::CODE . '/ipn_check_ip',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isActive(?int $storeId = null): bool
    {
        return (bool) $this->scopeConfig->isSetFlag(
            'payment/' . ConfigProvider::CODE . '/active',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSupportedCurrency(): string
    {
        return self::SUPPORTED_CURRENCY;
    }

    public function isCurrencySupported(string $currencyCode): bool
    {
        return strtoupper(trim($currencyCode)) === self::SUPPORTED_CURRENCY;
    }
}