<?php

declare(strict_types=1);

namespace SimPay\Magento\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use SimPay\Magento\Model\Config;

class AuthMethod implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::AUTH_METHOD_OAUTH, 'label' => __('Connect with SimPay (recommended)')],
            ['value' => Config::AUTH_METHOD_MANUAL, 'label' => __('Enter credentials manually')],
        ];
    }
}
