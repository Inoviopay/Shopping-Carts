/**
 * Inovio JS component 
 *
 * @category    Inovio
 * @author      Chetu India Team
 */
define(
        [
            'jquery',
            'Magento_Payment/js/view/payment/cc-form',
            'Magento_Checkout/js/action/place-order',
            'Magento_Checkout/js/model/quote',
            'Magento_Customer/js/model/customer',
            'Magento_Checkout/js/model/payment/additional-validators',
            'mage/url',
            'Magento_Checkout/js/model/full-screen-loader'
        ],
        function(
                $,
                Component,
                placeOrderAction,
                quote,
                customer,
                additionalValidators,
                url, fullScreenLoader
                ) {
            'use strict';
            return Component.extend({
                defaults: {
                    template: 'Inovio_Extension/payment/form'
                },
                getMailingAddress: function() {
                    return window.checkoutConfig.payment.checkmo.mailingAddress;
                },
                setPlaceOrderHandler: function(handler) {
                    this.placeOrderHandler = handler;
                },
                context: function() {
                    return this;
                },
                isShowLegend: function() {
                    return true;
                },
                getCode: function() {
                    return 'inovio_extension';
                },
                isActive: function() {
                    return true;
                }
            });
        }
);
