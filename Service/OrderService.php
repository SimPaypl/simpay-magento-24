<?php

declare(strict_types=1);

namespace SimPay\Magento\Service;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Framework\Exception\LocalizedException;

class OrderService
{
    private const STATUS_PAID    = 'transaction_paid';
    private const STATUS_FAILED  = ['transaction_expired', 'transaction_failed', 'transaction_canceled'];

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderFactory $orderFactory,
        private readonly TransactionFactory $transactionFactory,
        private readonly LoggerInterface $logger
    ) {}

    public function handleTransactionStatusChanged(array $data): void
    {
        $status = (string)($data['status'] ?? '');
        $control = (string)($data['control'] ?? '');

        if ($control === '') {
            throw new \RuntimeException('Missing control (order increment id).');
        }

        $order = $this->loadOrderByIncrementId($control);
        if (!$order) {
            throw new \RuntimeException('Order not found by increment_id: ' . $control);
        }

        $payment = $order->getPayment();
        if (!$payment) {
            throw new \RuntimeException('Order has no payment object.');
        }

        // --- PAID ---
        if ($status === self::STATUS_PAID) {
            $transactionId = (string)($data['id'] ?? '');
            if ($transactionId === '') {
                throw new \RuntimeException('Missing transaction id in IPN data.');
            }

            // Idempotency: consider payment applied only when Magento shows it as paid
            $alreadyApplied = ((int)$payment->getAdditionalInformation('simpay_paid_applied') === 1)
                || ($order->getTotalDue() <= 0.0001)
                || in_array($order->getState(), [Order::STATE_PROCESSING, Order::STATE_COMPLETE], true);

            if ($alreadyApplied) {
                return;
            }

            // Amount validation
            $paidAmount   = (float)($data['amount']['final_value'] ?? 0);
            $paidCurrency = (string)($data['amount']['final_currency'] ?? $order->getOrderCurrencyCode());
            $orderTotal   = (float)$order->getGrandTotal();

            if ($paidAmount > 0 && $paidAmount + 0.00001 < $orderTotal) {
                $order->addCommentToStatusHistory(sprintf(
                    'SimPay: payment amount mismatch. Expected %.2f %s, got %.2f %s. Transaction: %s',
                    $orderTotal,
                    (string)$order->getOrderCurrencyCode(),
                    $paidAmount,
                    $paidCurrency,
                    $transactionId
                ))->setIsCustomerNotified(false);

                $this->orderRepository->save($order);
                throw new \RuntimeException('Invalid payment amount (lower than order total).');
            }

            // Persist gateway ids on payment
            $payment->setTransactionId($transactionId);
            $payment->setLastTransId($transactionId);

            // Exit payment review when gateway confirms PAID
            if ($order->getState() === Order::STATE_PAYMENT_REVIEW) {
                $payment->setIsTransactionApproved(true);
                $payment->setIsTransactionDenied(false);

                // Available in many Magento versions; safe-guarded
                if (method_exists($payment, 'accept')) {
                    $payment->accept();
                }

                $order->setState(Order::STATE_PROCESSING);
                $order->setStatus(
                    $order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING) ?: 'processing'
                );
            }

            // Create invoice + capture
            if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                if (!$invoice || !$invoice->getTotalQty()) {
                    throw new \RuntimeException('Cannot create invoice (empty qty).');
                }

                $invoice->setTransactionId($transactionId);
                $invoice->setRequestedCaptureCase(Invoice::CAPTURE_ONLINE);
                $invoice->register();

                // Close payment transaction after capture
                $payment->setIsTransactionClosed(true);

                // Mark as applied (idempotency anchor)
                $payment->setAdditionalInformation('simpay_paid_applied', 1);

                $order->addCommentToStatusHistory(
                    sprintf('SimPay: payment captured (IPN). Transaction: %s', $transactionId)
                )->setIsCustomerNotified(false);

                $transaction = $this->transactionFactory->create();
                $transaction->addObject($invoice);
                $transaction->addObject($order);
                $transaction->save();
            } else {
                // Do NOT mark as applied if we couldn't invoice
                $order->addCommentToStatusHistory(
                    sprintf('SimPay: payment confirmed by IPN, but order cannot be invoiced. Transaction: %s', $transactionId)
                )->setIsCustomerNotified(false);

                $this->orderRepository->save($order);
                return;
            }

            // Store gateway info (after applying payment)
            $payment->setAdditionalInformation('simpay_transaction_id', $transactionId);
            $payment->setAdditionalInformation('simpay_status', $status);
            $payment->setAdditionalInformation('simpay_paid_amount', $paidAmount ?: $orderTotal);
            $payment->setAdditionalInformation('simpay_paid_currency', $paidCurrency ?: (string)$order->getOrderCurrencyCode());

            $this->orderRepository->save($order);
            return;
        }

        // --- FAILED ---
        if (in_array($status, self::STATUS_FAILED)) {
            $alreadyPaid = ((string)$payment->getAdditionalInformation('simpay_status') === self::STATUS_PAID);
            if ($alreadyPaid) {
                $this->logger->info('SimPay IPN: status ignored because order already marked failed', [
                    'increment_id' => $order->getIncrementId(),
                ]);
                return;
            }

            $payment->setAdditionalInformation('simpay_status', $status);

            $order->addCommentToStatusHistory('SimPay: transaction failed (IPN). Order will be cancelled.')
                ->setIsCustomerNotified(false);

            if ($order->canCancel()) {
                $order->cancel();
            } else {
                // fallback, if some custom flow blocks cancel()
                $order->setState(Order::STATE_CANCELED);
                $order->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_CANCELED) ?: 'canceled');
            }

            $transaction = $this->transactionFactory->create();
            $transaction->addObject($order);
            $transaction->addObject($payment);
            $transaction->save();

            return;
        }

        // Other statuses: keep log, but do not break anything
        $this->logger->info('SimPay IPN ignored status', ['status' => $status]);
    }

    public function handleTestNotification(): void
    {
        $this->logger->info('SimPay IPN test notification');
    }

    private function loadOrderByIncrementId(string $incrementId): ?Order
    {
        $order = $this->orderFactory->create();
        $order->loadByIncrementId($incrementId);

        return $order->getEntityId() ? $order : null;
    }
}