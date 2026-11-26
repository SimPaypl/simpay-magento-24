<?php

declare(strict_types=1);

namespace SimPay\Magento\Gateway\Http;

use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Psr\Log\LoggerInterface;
use SimPay\Magento\Model\Config;
use SimPay\Magento\Model\SimPayFactory;
use SimPay\SDK\Exception\ApiException;
use SimPay\SDK\SimPayClient;

/**
 * SimPay API calls of the payment gateway commands (see di.xml):
 * "transaction" creates a payment, "refund" creates a refund.
 */
class Client implements ClientInterface
{
    public const OPERATION_TRANSACTION = 'transaction';
    public const OPERATION_REFUND = 'refund';

    public function __construct(
        private readonly SimPayFactory $simPayFactory,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly string $operation = self::OPERATION_TRANSACTION
    ) {
    }

    public function placeRequest(TransferInterface $transferObject): array
    {
        $body = (array) $transferObject->getBody();
        $storeId = isset($body['store_id']) ? (int) $body['store_id'] : null;

        if ($this->config->getBearerToken($storeId) === '' || $this->config->getServiceId($storeId) === '') {
            throw new \RuntimeException('SimPay is not configured (missing API password / Service ID).');
        }

        $client = $this->simPayFactory->create($storeId)->client();

        try {
            return $this->send($client, $body);
        } catch (\Throwable $e) {
            $message = $e instanceof ApiException && $e->getApiMessage()
                ? sprintf('HTTP %d: %s', $e->getHttpStatusCode(), $e->getApiMessage())
                : $e->getMessage();

            $this->logger->critical('SimPay request failed', [
                'operation' => $this->operation,
                'error' => $message,
            ]);

            throw new \RuntimeException('SimPay request failed: ' . $message, 0, $e);
        }
    }

    private function send(SimPayClient $client, array $body): array
    {
        if ($this->operation === self::OPERATION_REFUND) {
            return $client->createRefund(
                (string) $body['transaction_id'],
                isset($body['amount']) ? (float) $body['amount'] : null
            );
        }

        return $client->createTransaction($body);
    }
}
