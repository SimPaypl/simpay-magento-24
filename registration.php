<?php
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

if (!class_exists(ComponentRegistrar::class)) {
    return;
}

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'SimPay_Magento',
    __DIR__
);