<?php
declare(strict_types=1);

namespace SimPay\Magento\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\StoreManagerInterface;

class WebhookUrl extends Field
{
    private StoreManagerInterface $storeManager;

    public function __construct(
        Context $context,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->storeManager = $storeManager;
    }

    /**
     * Render field as read-only note with webhook URL.
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $baseUrl = rtrim($this->storeManager->getStore()->getBaseUrl(), '/');
        $url = $baseUrl . '/simpay/ipn/index';

        // Simple admin-friendly rendering
        return sprintf(
            '<input type="text" readonly="readonly" value="%s" style="width: 600px; max-width: 100%%;" onclick="this.select();" />',
            $this->escapeHtml($url)
        );
    }
}