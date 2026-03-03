<?php

declare(strict_types=1);

namespace SimPay\Magento\Service\Ipn;

use SimPay\Magento\Service\OrderService;

class IpnDispatcher
{
    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function dispatch(array $payload): void
    {
        $type = (string)($payload['type'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        if ($type === '') {
            throw new \RuntimeException('Missing event type');
        }

        switch ($type) {
            case 'transaction:status_changed':
                $this->orderService->handleTransactionStatusChanged($data);
                return;

            case 'ipn:test':
                $this->orderService->handleTestNotification();
                return;

            default:
                throw new \RuntimeException('Unknown event type: ' . $type);
        }
    }
}