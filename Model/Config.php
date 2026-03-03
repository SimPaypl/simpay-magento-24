<?php

declare(strict_types=1);

namespace SimPay\Magento\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use SimPay\Magento\Model\Ui\ConfigProvider;

class Config
{
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
}