<?php

declare(strict_types=1);

namespace SimPay\Magento\Model\Ui;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public const CODE = 'simpay_magento';

    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly ScopeConfigInterface $scopeConfig
    )
    {
    }

    public function getConfig(): array
    {
        $title = (string)$this->scopeConfig->getValue(
            'payment/' . self::CODE . '/title',
            ScopeInterface::SCOPE_STORE
        );

        $isActiveRaw = $this->scopeConfig->getValue(
            'payment/' . self::CODE . '/active',
            ScopeInterface::SCOPE_STORE
        );
        $isActive = $isActiveRaw === null ? true : (bool) $isActiveRaw;

        return [
            'payment' => [
                self::CODE => [
                    'title' => $title ?: 'SimPay',
                    'redirectUrl' => $this->urlBuilder->getUrl('simpay/redirect/index', ['_secure' => true]),
                    'isActive' => $isActive,
                ],
            ],
        ];
    }
}