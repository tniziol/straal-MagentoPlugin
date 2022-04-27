define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
],function(Component,renderList){
    'use strict';
    renderList.push({
        type : 'straal_payment',
        component : 'Straal_Payment/js/view/payment/method-renderer/straal-method'
    });

    return Component.extend({});
})
