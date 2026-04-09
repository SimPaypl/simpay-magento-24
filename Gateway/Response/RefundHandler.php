<?php

declare(strict_types=1);

namespace SimPay\Magento\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;

class RefundHandler implements HandlerInterface
{
    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        $creditmemoIncrementId = (string) ($payment->getCreditmemo()?->getIncrementId() ?? '');

        $data = $response['data'] ?? $response;
        if (!is_array($data)) {
            $data = [];
        }

        $amount = 0.0;
        try {
            $amount = (float) SubjectReader::readAmount($handlingSubject);
        } catch (\Throwable) {
            $amount = 0.0;
        }

        $refundId = (string) ($data['refund_id'] ?? '');
        if ($refundId === '') {
            throw new \RuntimeException('SimPay refund response is missing refund_id.');
        }

        $refunds = $payment->getAdditionalInformation('simpay_refunds');
        if (!is_array($refunds)) {
            $refunds = [];
        }

        $refunds[$refundId] = [
            'refund_id' => $refundId,
            'status' => 'requested',
            'amount' => $amount,
            'creditmemo_increment_id' => $creditmemoIncrementId,
            'updated_at' => gmdate(DATE_ATOM),
        ];

        $payment->setAdditionalInformation('simpay_refunds', $refunds);

        $payment->setAdditionalInformation('simpay_last_refund_id', $refundId);

        if ($order) {
            $order->addCommentToStatusHistory(sprintf(
                'SimPay: refund request created (refund_id: %s)%s',
                $refundId,
                $creditmemoIncrementId !== '' ? ', credit memo: ' . $creditmemoIncrementId : ''
            ))->setIsCustomerNotified(false);
        }
    }
}




