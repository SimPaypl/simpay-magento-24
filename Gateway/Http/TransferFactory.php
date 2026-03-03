<?php

declare(strict_types=1);

namespace SimPay\Magento\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use SimPay\Magento\Model\Config;

class TransferFactory implements TransferFactoryInterface
{
    public function __construct(
        private readonly TransferBuilder $transferBuilder,
        private readonly Config $config
    ) {
    }

    public function create(array $request): TransferInterface
    {
        $serviceId = (string) $this->config->getServiceId();

        if ($serviceId === '') {
            throw new \RuntimeException('SimPay Service ID is missing.');
        }

        $uri = sprintf('https://api.simpay.pl/payment/%s/transactions', rawurlencode($serviceId));

        return $this->transferBuilder
            ->setUri($uri)
            ->setMethod('POST')
            ->setHeaders([
                'Accept' => 'application/json; charset=utf-8',
                'Content-Type' => 'application/json; charset=utf-8',
            ])
            ->setClientConfig([
                'timeout' => 30,
            ])
            ->setBody($request)
            ->build();
    }
}