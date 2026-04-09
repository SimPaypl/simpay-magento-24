<?php

declare(strict_types=1);

namespace SimPay\Magento\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;

class RefundRequestBuilder implements BuilderInterface
{
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment = $paymentDO->getPayment();

        $transactionId = $this->resolveTransactionId($payment);
        if ($transactionId === '') {
            throw new \RuntimeException('SimPay refund is unavailable: missing original transaction id.');
        }

        $request = [
            'transaction_id' => $transactionId,
        ];

        try {
            $amount = (float) SubjectReader::readAmount($buildSubject);
            if ($amount > 0.0) {
                $request['amount'] = (float) number_format($amount, 2, '.', '');
            }
        } catch (\Throwable) {
            // Amount is optional: missing value means full refund.
        }

        return $request;
    }

    private function resolveTransactionId($payment): string
    {
        $transactionId = (string) $payment->getAdditionalInformation('simpay_transaction_id');
        if ($transactionId !== '') {
            return $transactionId;
        }

        $transactionId = (string) $payment->getLastTransId();
        if ($transactionId !== '') {
            return $transactionId;
        }

        return (string) $payment->getTransactionId();
    }
}





