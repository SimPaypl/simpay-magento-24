<?php

declare(strict_types=1);

namespace SimPay\Magento\Service\Ipn;

use SimPay\Magento\Service\OrderService;
use SimPay\SDK\IpnPayload;
use SimPay\SDK\PaymentStatus;

class IpnDispatcher
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function dispatch(IpnPayload $ipn): void
    {
        switch ($ipn->type) {
            case PaymentStatus::TYPE_TRANSACTION_STATUS:
                $this->orderService->handleTransactionStatusChanged($ipn->data);
                return;

            case PaymentStatus::TYPE_REFUND_STATUS:
                $this->orderService->handleRefundStatusChanged($ipn->data);
                return;

            case 'ipn:test':
                $this->orderService->handleTestNotification();
                return;

            default:
                throw new \RuntimeException('Unknown event type: ' . $ipn->type);
        }
    }
}