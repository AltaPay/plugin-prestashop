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
        var payment_option = $('input[type="radio"][name="payment-option"]:checked').attr('id');
        var terminalId = $("#"+payment_option+"-additional-information > #hidden-terminalid").text();
        if(terminalId) {
            onApplePayButtonClicked();
            return false;
        }
    });

    function onApplePayButtonClicked() { 
        var payment_option = $('input[type="radio"][name="payment-option"]:checked').attr('id');
        var terminalId = $("#"+payment_option+"-additional-information > #hidden-terminalid").text();
        if (!ApplePaySession) {
            return;
        }
        
        // Define ApplePayPaymentRequest
        const request = {
            "countryCode": countryCode,
            "currencyCode": currencyCode,
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
                "amount": amountPaid
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
            let total = {
                "label": "ApplePay Altapay",
                "type": "final",
                "amount": amountPaid
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
            $.ajax({
                url: cardwalletresponseurl,
                data: {
                    providerData: JSON.stringify(event.payment.token),
                    method: terminalId
                },
                type: 'post',
                dataType: 'JSON',
                complete: function(response) {
                    var status
                    var responseData = response.responseJSON;
                    if((responseData.status === "Success") && (response.status == 200)) {
                        status = ApplePaySession.STATUS_SUCCESS;
                        session.completePayment(status);
                        window.location.replace(responseData.redirectUrl);
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