/*browser:true*/
/*global define*/
define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';

    rendererList.push({
        type: 'simpay_magento',
        component: 'SimPay_Magento/js/view/payment/method-renderer/simpay_magento',
        sortOrder: 350
    });

    return Component.extend({});
});