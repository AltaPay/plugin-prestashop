/*
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
document.addEventListener('DOMContentLoaded', function (event) {
    let session = "";

    $('body').on('submit', '.tc-main-title.selected form', function (e) {
        if ($('.tc-main-title.selected #applepay-terminalid').text()) {
            e.preventDefault();
            const terminalId = activeTerminalId();
            onApplePayButtonClicked(terminalId, false, true);
        }
    });

    $('body').on('click', '#confirm_order', function (e) {
        const terminalId = activeTerminalId();
        if (terminalId) {
            onApplePayButtonClicked(terminalId, true, false);
        }
    });

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
        var terminalId = activeTerminalId();
        if(terminalId) {
            onApplePayButtonClicked(terminalId, true, true);
            return false;
        }
    });

    function activeTerminalId(){
        const payment_option = $('input[type="radio"][name="payment-option"]:checked').attr('id');
        const terminalId = $("#" + payment_option + "-additional-information > #applepay-terminalid").text();

        return terminalId;
    }
    
    function onApplePayButtonClicked(terminalId, createSession, beginSession) {
        if (!ApplePaySession) {
            return;
        }
        
        // Update value for amountPaid and currencyCode from prestashop object
        if(typeof prestashop != "undefined"){
            if(prestashop.hasOwnProperty('cart') && prestashop.cart.hasOwnProperty('totals')){
                amountPaid = prestashop.cart.totals.total.amount;
            }

            if(prestashop.hasOwnProperty('currency') && prestashop.cart.hasOwnProperty('iso_code')){
                currencyCode = prestashop.currency.iso_code;
            }
        }
        
        // Define ApplePayPaymentRequest
        const request = {
            "countryCode": countryCode,
            "currencyCode": currencyCode,
            "merchantCapabilities": [
                "supports3DS"
            ],
            "supportedNetworks": JSON.parse(applepaySupportedNetworks),
            "total": {
                "label": applepayLabel,
                "type": "final",
                "amount": amountPaid
            }
        };
        
        // Create ApplePaySession
        if (createSession) {
            session = new ApplePaySession(3, request);
        }
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
                "label": applepayLabel,
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
                    method: terminalId,
                    is_apple_pay: true
                },
                type: 'post',
                dataType: 'JSON',
                success: function(response) {
                    if(response && response.status === "Success") {
                        session.completePayment(ApplePaySession.STATUS_SUCCESS);
                        window.location.replace(response.redirectUrl);
                    } else {
                        session.completePayment(ApplePaySession.STATUS_FAILURE); 
                    }
                },
                error: function (response) {
                    session.completePayment(ApplePaySession.STATUS_FAILURE);
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

        if (beginSession) {
            session.begin();
        }

    }
});