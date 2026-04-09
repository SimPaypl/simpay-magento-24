<?php

declare(strict_types=1);

namespace SimPay\Magento\Service;

use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class OrderService
{
    private const STATUS_PAID    = 'transaction_paid';
    private const STATUS_FAILED  = ['transaction_expired', 'transaction_failure', 'transaction_canceled'];
    private const REFUND_PENDING = ['refund_new', 'refund_pending'];
    private const REFUND_SUCCESS = ['refund_completed'];
    private const REFUND_FAILED = ['refund_failed', 'refund_rejected'];

    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderFactory $orderFactory,
        private readonly OrderCollectionFactory $orderCollectionFactory,
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

            // Let Magento register capture/invoice through payment notification API.
            $captureAmount = $paidAmount > 0 ? $paidAmount : $orderTotal;
            $payment->setIsTransactionClosed(false);
            $payment->setShouldCloseParentTransaction(true);
            $payment->registerCaptureNotification($captureAmount, true);

            // Mark as applied (idempotency anchor)
            $payment->setAdditionalInformation('simpay_paid_applied', 1);

            $order->addCommentToStatusHistory(
                sprintf('SimPay: capture notification registered (IPN). Transaction: %s', $transactionId)
            )->setIsCustomerNotified(false);

            $transaction = $this->transactionFactory->create();
            $transaction->addObject($payment);
            $transaction->addObject($order);
            $transaction->save();

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

    public function handleRefundStatusChanged(array $data): void
    {
        $status = (string) ($data['status'] ?? '');
        $refundId = (string) ($data['id'] ?? ($data['refund_id'] ?? ''));
        $control = (string) ($data['control'] ?? '');
        $transactionId = (string) ($data['transaction']['id'] ?? ($data['transaction_id'] ?? ''));
        $refundAmount = (string) ($data['amount']['value'] ?? '');

        $order = null;
        if ($control !== '') {
            $order = $this->loadOrderByIncrementId($control);
        }

        if (!$order && $transactionId !== '') {
            $order = $this->loadOrderByTransactionId($transactionId);
        }

        if (!$order) {
            throw new \RuntimeException(sprintf(
                'Order not found for refund notification (control: %s, transaction_id: %s).',
                $control !== '' ? $control : 'N/A',
                $transactionId !== '' ? $transactionId : 'N/A'
            ));
        }

        $payment = $order->getPayment();
        if (!$payment) {
            throw new \RuntimeException('Order has no payment object.');
        }

        if ($refundId === '') {
            throw new \RuntimeException('Missing refund id in refund notification payload.');
        }

        $refunds = $payment->getAdditionalInformation('simpay_refunds');
        if (!is_array($refunds)) {
            $refunds = [];
        }

        $mappedStatus = $status;
        if (in_array($status, self::REFUND_PENDING, true)) {
            $mappedStatus = 'pending';
        } elseif (in_array($status, self::REFUND_SUCCESS, true)) {
            $mappedStatus = 'completed';
        } elseif (in_array($status, self::REFUND_FAILED, true)) {
            $mappedStatus = 'failed';
        }

        $refunds[$refundId] = array_merge(
            is_array($refunds[$refundId] ?? null) ? $refunds[$refundId] : [],
            [
                'refund_id' => $refundId,
                'status' => $mappedStatus,
                'ipn_status' => $status,
                'transaction_id' => $transactionId,
                'amount' => $refundAmount,
                'updated_at' => gmdate(DATE_ATOM),
            ]
        );

        $payment->setAdditionalInformation('simpay_refunds', $refunds);
        $payment->setAdditionalInformation('simpay_last_refund_status', $mappedStatus);

        $order->addCommentToStatusHistory(sprintf(
            'SimPay: refund status changed (IPN): %s%s%s%s',
            $status !== '' ? $status : 'unknown',
            $refundId !== '' ? ' [refund_id: ' . $refundId . ']' : '',
            $transactionId !== '' ? ' [transaction_id: ' . $transactionId . ']' : '',
            $refundAmount !== '' ? ' [amount: ' . $refundAmount . ']' : ''
        ))->setIsCustomerNotified(false);

        $this->orderRepository->save($order);
    }

    private function loadOrderByIncrementId(string $incrementId): ?Order
    {
        $order = $this->orderFactory->create();
        $order->loadByIncrementId($incrementId);

        return $order->getEntityId() ? $order : null;
    }

    private function loadOrderByTransactionId(string $transactionId): ?Order
    {
        if ($transactionId === '') {
            return null;
        }

        $collection = $this->orderCollectionFactory->create();
        $paymentTable = $collection->getTable('sales_order_payment');

        $collection->getSelect()
            ->join(
                ['payment' => $paymentTable],
                'payment.parent_id = main_table.entity_id',
                []
            )
            ->where('payment.last_trans_id = ?', $transactionId)
            ->orWhere('payment.transaction_id = ?', $transactionId)
            ->orWhere('payment.additional_information LIKE ?', '%' . $transactionId . '%');

        $collection->setPageSize(1);
        $order = $collection->getFirstItem();

        return $order && $order->getEntityId() ? $order : null;
    }
}