<?php

declare(strict_types=1);

namespace SimPay\Magento\Gateway\Http;

use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;

/**
 * Passes the built request to the client - URLs, headers and auth are handled by the SDK.
 */
class TransferFactory implements TransferFactoryInterface
{
    public function __construct(
        private readonly TransferBuilder $transferBuilder
    ) {
    }

    public function create(array $request): TransferInterface
    {
        return $this->transferBuilder->setBody($request)->build();
    }
}
