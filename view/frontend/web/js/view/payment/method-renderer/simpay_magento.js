/*browser:true*/
/*global define*/
define([
    'Magento_Checkout/js/view/payment/default',
    'ko',
    'mage/url'
], function (Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'SimPay_Magento/payment/simpay_magento',
            simpayAgreement: ko.observable(true)
        },

        redirectAfterPlaceOrder: false,

        getCode: function () {
            return 'simpay_magento';
        },

        afterPlaceOrder: function () {
            var cfg = (window.checkoutConfig && window.checkoutConfig.payment) || {};
            var redirectUrl = (cfg[this.getCode()] && cfg[this.getCode()].redirectUrl) || '';
            window.location.replace(redirectUrl || window.checkoutConfig.defaultSuccessPageUrl);
        },

        getLogoUrl: function () {
            return require.toUrl('SimPay_Magento/images/logo.svg');
        },

        /**
         * Payload sent to Magento when placing order (SimPay)
         */
        getData: function () {
            return {
                method: this.getCode(),
                additional_data: {
                    simpay_agreement: this.simpayAgreement() ? 1 : 0
                }
            };
        }
    });
});