<?php

declare(strict_types=1);

namespace SimPay\Magento\Gateway\Request;

use Magento\Framework\UrlInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address;
use Psr\Log\LoggerInterface;
use SimPay\Magento\Model\Config;
use Magento\Customer\Model\CustomerFactory;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

class OrderRequestBuilder implements BuilderInterface
{
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config,
        private readonly Request $request,
        private readonly LoggerInterface $logger,
        private readonly CustomerFactory $customerFactory,
        private readonly OrderCollectionFactory $orderCollectionFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function build(array $buildSubject): array
    {

        $this->logger->alert('SimPay request builder start', [
        ]);

        $paymentDO = SubjectReader::readPayment($buildSubject);
        $order = $paymentDO->getOrder();

        $billing = $order->getBillingAddress();
        $shipping = $order->getShippingAddress();

        $customer = $this->resolveCustomerFromAddress($billing, $shipping);

        $userAgent = (string) $this->request->getServer('HTTP_USER_AGENT');

        $returnUrl = $this->urlBuilder->getUrl('checkout/onepage/success', ['_secure' => true]);
        $this->logger->alert('SimPay request builder end', [
        ]);

        $context = [];

        if ((int) $order->getCustomerId() > 0) {
            $context = $this->buildTwistoContext(
                (string) $order->getCurrencyCode(),
                (string) $order->getOrderIncrementId(),
                $customer['email']
            );
        }

        return [
            'amount' => (float) $order->getGrandTotalAmount(),
            'currency' => (string) $order->getCurrencyCode(),
            'description' => __('Order #%1', $order->getOrderIncrementId()),
            'control' => (string) $order->getOrderIncrementId(),

            // customer
            'customer' => [
                'name' => $customer['name'],
                'email' => $customer['email'],
                'ip' => (string) $order->getRemoteIp()
            ],

            'antifraud' => [
                'useragent' => $userAgent,
            ],

            'billing' => $this->mapAddress($billing),
            'shipping' => $this->mapAddress($shipping),

            'context' => $context,

            'returns' => [
                'success' => $returnUrl,
                'failure' => $returnUrl,
            ],
        ];
    }

    private function buildTwistoContext(string $currency, string $currentIncrementId, string $email): array
    {
        $context = [
            'accountSetCurrency' => $currency,
        ];

        if ($email === '') {
            return $context;
        }

        $store = $this->storeManager->getStore();
        $websiteId = (int) $store->getWebsiteId();

        // Try to load Magento customer by email
        $customer = $this->customerFactory->create()
            ->setWebsiteId($websiteId)
            ->loadByEmail($email);

        if ($customer->getId() && $customer->getCreatedAt()) {
            try {
                $context['accountCreatedAt'] = (new \DateTimeImmutable((string) $customer->getCreatedAt()))
                    ->format(\DateTimeInterface::ATOM);
            } catch (\Throwable) {
                // Ignore invalid date format
            }
        }

        $stats = $this->collectCustomerStats($email, $currentIncrementId);

        $context['salesTotalCount'] = $stats['salesTotalCount'];
        $context['salesTotalAmount'] = $this->formatDecimal($stats['salesTotalAmount']);
        $context['salesAvgAmount'] = $this->formatDecimal($stats['salesAvgAmount']);
        $context['salesMaxAmount'] = $this->formatDecimal($stats['salesMaxAmount']);
        $context['refundsTotalAmount'] = $this->formatDecimal($stats['refundsTotalAmount']);
        $context['hasPreviousPurchases'] = $stats['salesTotalCount'] > 0;

        return $context;
    }

    private function collectCustomerStats(string $email, string $currentIncrementId): array
    {
        $collection = $this->orderCollectionFactory->create();

        $collection->addFieldToSelect([
            'increment_id',
            'grand_total',
            'total_refunded',
            'state',
            'customer_email',
        ]);

        $collection->addFieldToFilter('customer_email', $email);
        $collection->addFieldToFilter('increment_id', ['neq' => $currentIncrementId]);
        $collection->addFieldToFilter('state', ['in' => ['processing', 'complete', 'closed']]);

        $salesTotalCount = 0;
        $salesTotalAmount = 0.0;
        $salesMaxAmount = 0.0;
        $refundsTotalAmount = 0.0;

        foreach ($collection as $historicalOrder) {
            $amount = (float) $historicalOrder->getGrandTotal();
            $refunded = (float) $historicalOrder->getTotalRefunded();

            $salesTotalCount++;
            $salesTotalAmount += $amount;
            $refundsTotalAmount += $refunded;

            if ($amount > $salesMaxAmount) {
                $salesMaxAmount = $amount;
            }
        }

        $salesAvgAmount = $salesTotalCount > 0
            ? $salesTotalAmount / $salesTotalCount
            : 0.0;

        return [
            'salesTotalCount' => $salesTotalCount,
            'salesTotalAmount' => $salesTotalAmount,
            'salesAvgAmount' => $salesAvgAmount,
            'salesMaxAmount' => $salesMaxAmount,
            'refundsTotalAmount' => $refundsTotalAmount,
        ];
    }

    private function formatDecimal(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    private function mapAddress($address): array
    {
        if (!$address) {
            return [];
        }

        $street = $address->getStreet();
        $streetLine1 = is_array($street) && isset($street[0]) ? (string) $street[0] : (string) $street;

        return [
            'name' => (string) $address->getFirstname(),
            'surname' => (string) $address->getLastname(),
            'street' => $streetLine1,
            'city' => (string) $address->getCity(),
            'postalCode' => (string) $address->getPostcode(),
            'country' => (string) $address->getCountryId(),
            'company' => (string) $address->getCompany(),
        ];
    }

    private function resolveCustomerFromAddress($billing, $shipping): array
    {
        $address = $billing ?: $shipping;
        if (!$address) {
            return ['name' => '', 'email' => ''];
        }

        $name = trim((string) $address->getFirstname() . ' ' . (string) $address->getLastname());
        $email = (string) $address->getEmail();

        return [
            'name' => $name,
            'email' => $email,
        ];
    }
}
