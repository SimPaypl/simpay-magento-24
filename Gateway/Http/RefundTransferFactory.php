<?php
declare(strict_types=1);
namespace SimPay\Magento\Gateway\Http;
use Magento\Payment\Gateway\Http\TransferBuilder;
use Magento\Payment\Gateway\Http\TransferFactoryInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use SimPay\Magento\Model\Config;
class RefundTransferFactory implements TransferFactoryInterface
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
        $transactionId = (string) ($request['transaction_id'] ?? '');
        if ($transactionId === '') {
            throw new \RuntimeException('SimPay refund cannot be created: missing transaction id.');
        }
        $uri = sprintf(
            'https://api.simpay.pl/payment/%s/transactions/%s/refunds',
            rawurlencode($serviceId),
            rawurlencode($transactionId)
        );

        $body = [];

        $amount = isset($request['amount']) ? (float) $request['amount'] : 0.0;
        if ($amount > 0.0) {
            $body['amount'] = (float) number_format($amount, 2, '.', '');
        }

        return $this->transferBuilder
            ->setUri($uri)
            ->setMethod('POST')
            ->setClientConfig([
                'timeout' => 30,
            ])
            ->setBody($body)
            ->build();
    }
}
