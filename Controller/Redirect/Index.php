<?php

declare(strict_types=1);

namespace SimPay\Magento\Controller\Redirect;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Controller\ResultFactory;

class Index extends Action
{
    public function __construct(
        Context $context,
        private readonly CheckoutSession $checkoutSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);

        $order = $this->checkoutSession->getLastRealOrder();
        if (!$order || !$order->getId()) {
            return $resultRedirect->setPath('checkout/cart');
        }


        $payment = $order->getPayment();
        $url = (string) $payment->getAdditionalInformation('simpay_redirect_url');

        if ($url === '') {
            return $resultRedirect->setPath('checkout/onepage/success');
        }

        return $resultRedirect->setUrl($url);
    }
}