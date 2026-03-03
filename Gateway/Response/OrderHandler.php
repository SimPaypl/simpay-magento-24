<?php

declare(strict_types=1);

namespace SimPay\Magento\Gateway\Response;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Transaction;

class OrderHandler implements HandlerInterface
{
    public const KEY_REDIRECT_URL = 'simpay_redirect_url';

    public function handle(array $handlingSubject, array $response): void
    {
        $paymentDO = SubjectReader::readPayment($handlingSubject);
        $payment = $paymentDO->getPayment();

        $data = $response['data'] ?? $response;
        if (!is_array($data)) {
            $data = [];
        }

        $transactionId = (string)($data['transactionId'] ?? '');
        $redirectUrl   = (string)($data['redirectUrl'] ?? '');

        if ($transactionId !== '') {
            $payment->setTransactionId($transactionId);
            $payment->setLastTransId($transactionId);

            // Keep it open - final state comes from IPN/return
            $payment->setIsTransactionClosed(false);
            $payment->setIsTransactionPending(true);
            $payment->setShouldCloseParentTransaction(false);

            $order = $payment->getOrder();
            if ($order) {
                $order->addCommentToStatusHistory(
                    sprintf('SimPay: transaction created (%s). Waiting for payment confirmation.', $transactionId),
                    null,
                    false
                );
            }
        }

        if ($redirectUrl !== '') {
            $payment->setAdditionalInformation(self::KEY_REDIRECT_URL, $redirectUrl);
        }
    }
}