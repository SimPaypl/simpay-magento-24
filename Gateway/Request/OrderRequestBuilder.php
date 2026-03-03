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

class OrderRequestBuilder implements BuilderInterface
{
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly Config $config,
        private readonly Request $request,
        private readonly LoggerInterface $logger
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
        return [
            'amount' => (float) $order->getGrandTotalAmount(),
            'currency' => (string) $order->getCurrencyCode(),
            'description' => sprintf('Order #%s', (string) $order->getOrderIncrementId()),
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

            'returns' => [
                'success' => $returnUrl,
                'failure' => $returnUrl,
            ],
        ];
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
