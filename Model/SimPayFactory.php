<?php

declare(strict_types=1);

namespace SimPay\Magento\Model;

use Magento\Framework\App\ProductMetadataInterface;
use SimPay\SDK\SimPay;

/**
 * SDK instance configured with the credentials of the given (or current) store.
 */
class SimPayFactory
{
    public function __construct(
        private readonly Config $config,
        private readonly SimPayCache $cache,
        private readonly ProductMetadataInterface $productMetadata
    ) {
    }

    public function create(?int $storeId = null): SimPay
    {
        return new SimPay(
            bearerToken: $this->config->getBearerToken($storeId),
            serviceId: $this->config->getServiceId($storeId),
            signatureKey: $this->config->getIpnSignatureKey($storeId),
            platform: 'magento',
            platformVersion: (string) $this->productMetadata->getVersion(),
            cache: $this->cache
        );
    }
}
