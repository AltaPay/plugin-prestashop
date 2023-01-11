/*
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

$(function () {
    $("select.selectCreditCard").change(function () {
        var selectedCreditCard = $(this).children("option:selected").val();
        Cookies.set('selectedCreditCard', selectedCreditCard);
    });
    $(".savecard").change(function () {
        var savecard = 0;
        if($('.savecard').is(":checked")) {
            savecard = 1;
        }
        Cookies.set('savecard', savecard);
    });
    $("#payment-confirmation button").click(function (e) {
        onApplePayButtonClicked();
        return false;
    });

    function onApplePayButtonClicked() { 

        if (!ApplePaySession) {
            return;
        }
        
        // Define ApplePayPaymentRequest
        // TODO will add dynamic value once start working on PL-698
        const request = {
            "countryCode": "US",
            "currencyCode": "USD",
            "merchantCapabilities": [
                "supports3DS"
            ],
            "supportedNetworks": [
                "visa",
                "masterCard",
                "amex",
                "discover"
            ],
            "total": {
                "label": "Demo (Card is not charged)",
                "type": "final",
                "amount": "1.99"
            }
        };
        
        // Create ApplePaySession
        const session = new ApplePaySession(3, request);
        session.onvalidatemerchant = async event => {
            // Call your own server to request a new merchant session.
            $.ajax({
                url: baseDir,
                async: true,
                cache: false,
                dataType : "json",
                type: 'post',
                data: {
                    validationUrl: event.validationURL,
                    termminalid: Cookies.get('selectedPaymentId')
                },
                success: function(response) {
                    var responsedata = jQuery.parseJSON(response);
                    session.completeMerchantValidation(responsedata);
                },
                error: function(res,textStatus,jqXHR)
                {
                    console.log("TECHNICAL ERROR"+JSON.stringify(res));
                }
            });
        };
        
        session.onpaymentmethodselected = event => {
            // Define ApplePayPaymentMethodUpdate based on the selected payment method.
            // No updates or errors are needed, pass an empty object.
            const update = {};
            session.completePaymentMethodSelection(update);
        };
        
        session.onshippingmethodselected = event => {
            // Define ApplePayShippingMethodUpdate based on the selected shipping method.
            // No updates or errors are needed, pass an empty object. 
            const update = {};
            session.completeShippingMethodSelection(update);
        };
        
        session.onshippingcontactselected = event => {
            // Define ApplePayShippingContactUpdate based on the selected shipping contact.
            const update = {};
            session.completeShippingContactSelection(update);
        };
        
        session.onpaymentauthorized = event => {
            // Define ApplePayPaymentAuthorizationResult
            const result = {
                "status": ApplePaySession.STATUS_SUCCESS
            };
            session.completePayment(result);
        };
        
        session.oncouponcodechanged = event => {
            // Define ApplePayCouponCodeUpdate
            const newTotal = calculateNewTotal(event.couponCode);
            const newLineItems = calculateNewLineItems(event.couponCode);
            const newShippingMethods = calculateNewShippingMethods(event.couponCode);
            const errors = calculateErrors(event.couponCode);
            
            session.completeCouponCodeChange({
                newTotal: newTotal,
                newLineItems: newLineItems,
                newShippingMethods: newShippingMethods,
                errors: errors,
            });
        };
        
        session.oncancel = event => {
            // Payment cancelled by WebKit
        };
        
        session.begin();
    }
});