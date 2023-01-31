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
    $('#payment-confirmation > .ps-shown-by-js > button').click(function(e) {
        onApplePayButtonClicked();
        return false;
    });

    function onApplePayButtonClicked() { 
        var payment_option = $('input[type="radio"][name="payment-option"]:checked').attr('id');
        var terminalId = $("#"+payment_option+"-additional-information > #hidden-terminalid").text();
        if (!ApplePaySession) {
            return;
        }
        
        // TODO: get value dynamically in PL-698
        // Define ApplePayPaymentRequest
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
                url: cardwalleturl,
                async: true,
                cache: false,
                dataType : "json",
                type: 'post',
                data: {
                    validationUrl: event.validationURL,
                    termminalid: terminalId
                },
                success: function(response) {
                    if (response.success === true) {
                        var responsedata = jQuery.parseJSON(response.applePaySession);
                        session.completeMerchantValidation(responsedata);
                      } else {
                          console.log(jQuery.parseJSON(response.error));
                      }
                }
            });
        };
        
        session.onpaymentmethodselected = event => {
            // Define ApplePayPaymentMethodUpdate based on the selected payment method.
            // No updates or errors are needed, pass an empty object.
            // const update = {};
            // session.completePaymentMethodSelection(update);
            let total = {
                "label": "ApplePay Altapay",
                "type": "final",
                "amount": "1.99"
            }
    
            const update = { "newTotal": total };
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
            // const result = {
            //     "status": ApplePaySession.STATUS_SUCCESS
            // };
            // session.completePayment(result);
            // var method = this.terminal.substr(this.terminal.indexOf(" ") + 1);
            var url = cardwalletresponseurl;   
            console.log(event.payment.token); 
            $.ajax({
                url: url,
                data: {
                    providerData: JSON.stringify(event.payment.token),
                    paytype: terminalId
                },
                type: 'post',
                dataType: 'JSON',
                complete: function(response) {
                    var status;
                    var responsestatus = response.responseJSON.Result.toLowerCase();
                    if (responsestatus === 'success') {
                        status = ApplePaySession.STATUS_SUCCESS;
                        session.completePayment(status);
                        // redirectOnSuccessAction.execute();
                    } else {
                        status = ApplePaySession.STATUS_FAILURE;
                        session.completePayment(status);
                    }
                }
            });  
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