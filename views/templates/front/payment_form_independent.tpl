{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2026 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout</title>
    <style>
        body {
            margin: 0;
            font-family: Sans-Serif;
            background-color: #f9f9f9;
        }

        .header-minimal {
            width: 100%;
            color: #000;
            display: flex;
            height: 150px;
            justify-content: center;
            background-color: #dedede;
        }

        .header-minimal-logo {
            max-width: 200px;
            height: auto;
            margin: auto;
        }

        .header-minimal-headline {
            margin: auto;
        }

        .header-minimal-logo img {
            width: 100%;
            max-height: 140px;
        }
    </style>
    <style>
        .content-wrapper {
            max-width: 400px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px 50px;
        }

        .pensio_payment_form_label_cell {
            font-weight: bold;
            font-size: 14px;
        }

        .pensio_payment_form_row {
            margin-top: 20px;
        }

        #CreditCard {
            width: 100%;
            overflow: hidden;
        }

        #cardholderNameInput,
        #creditCardNumberInput {
            width: 100%;
        }

        #selectCardLabel,
        #creditCardTypeSecondIcon,
        #creditCardTypeIcon {
            display: none;
        }

        #pensioCreditCardPaymentSubmitButton,
        #submitbutton,
        #MobilePaymentFormSubmit {
            border-radius: 0;
            padding: 15px;
            width: 100%;
            border: none;
        }

        td.pensio_payment_form_label_cell {
            vertical-align: top;
        }

        td.pensio_payment_form_input_cell {
            display: flex;
            flex-direction: column;
        }

        #pensioCreditCardPaymentSubmitButton:not(:disabled),
        #submitbutton:not(:disabled),
        #MobilePaymentFormSubmit:not(:disabled) {
            background-color: #40ba8d;
            color: #fff;
        }

        #pensioCreditCardPaymentSubmitButton:not(:disabled):hover,
        #submitbutton:not(:disabled):hover,
        #MobilePaymentFormSubmit:not(:disabled):hover {
            background-color: #47d5a0;
        }

        #pensioCreditCardPaymentSubmitButton:disabled,
        #MobilePaymentFormSubmit:disabled {
            background-color: #dedede;
        }

        #cvcInput {
            width: 50%;
        }

        .pensio_payment_form_input_cell select {
            width: 40%;
            padding: 5px 0;
        }

        .custom-label {
            font-size: 13px;
        }

        input[type="text"],
        input[type="tel"],
        select {
            border-width: 0 0 1px;
            border-color: #000;
            padding-inline: 0;
        }

        input[type="text"]:focus-visible,
        input[type="tel"]:focus-visible,
        select:focus-visible {
            outline: none;
        }

        .surcharge-amount {
            visibility: hidden;
        }

        .Surcharged .surcharge-amount {
            visibility: visible;
        }

        .altapay-back-to-shop {
            margin: 15px 0;
            text-align: left;
        }

        .altapay-back-to-shop a {
            text-decoration: none;
        }

        @media screen and (max-width: 425px) {
            .content-wrapper {
                padding: 20px;
            }
        }

        #PensioJavascriptDisabledSurchargeNotice,
        #invalid_cvc,
        #invalid_cardholdername {
            color: red;
            background-color: white;
        }

        .info-wrapper, div#savecreditcard,
        .pensio_payment_form_row.expiry_row {
            text-align: left;
        }

        #cvcInput {
            width: 100%;
        }

        .checkout-style .pensio_payment_form_row {
            margin-top: 0;
        }

        .checkout-style .pensio_payment_form_card-number input,
        .checkout-style .pensio_payment_form_cardholder input,
        .checkout-style .pensio_payment_form_input_cell input {
            box-sizing: border-box;
            color: #666;
            box-shadow: none !important;
        }

        .checkout-style .pensioCreditCardInput {
            color: #666;
        }

        .checkout-style .pensio_payment_form_month select,
        .checkout-style .pensio_payment_form_year select,
        .checkout-style #idealIssuer {
            border-radius: 3px;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: linear-gradient(45deg, transparent 50%, black 50%),
            linear-gradient(135deg, black 50%, transparent 50%);
            background-position: calc(100% - 20px) calc(20px + 2px),
            calc(100% - 15px) calc(20px + 2px),
            100% 0;
            background-size: 5px 5px, 5px 5px, 40px 40px;
            background-repeat: no-repeat;
        }

        .checkout-style .pensio_payment_form_card-number input,
        .checkout-style .pensio_payment_form_cardholder input,
        .checkout-style .pensio_payment_form_input_cell input,
        .checkout-style .pensio_payment_form_month select,
        .checkout-style .pensio_payment_form_year select,
        .checkout-style #idealIssuer,
        .checkout-style .pensio_payment_form-cvc-input input#cvcInput {
            border: 1px solid rgba(0, 0, 0, 0.16);
            border-radius: 3px;
            padding: 15px;
            font-size: 16px;
            cursor: pointer;
            margin-top: 4px;
            width: 100%;
            height: auto;
        }

        .checkout-style .pensio_payment_form_expiration {
            display: flex;
            width: 100%;
            gap: 0 10px;
        }

        .checkout-style .pensio_payment_form_month {
            width: 30%;

        }

        .checkout-style .pensio_payment_form_year {
            width: 30%;

        }

        .checkout-style .pensio_payment_form_cvc {
            width: 40%;
        }

        .checkout-style .pensio_payment_form-cvc-input {
            display: flex;
            position: relative;
        }

        .checkout-style .cvc-icon {
            width: 30px;
            position: absolute;
            top: 20px;
            right: 15px;
        }

        .checkout-style .credit-card-visa-icon {
            position: absolute;
            top: 0;
            right: 0;
            display: flex;
            padding-right: 7px;
            padding-top: 14px;
            align-items: center;
        }

        .checkout-style .credit-card-mastercard-icon {
            position: absolute;
            top: 0;
            right: 0;
            display: flex;
            padding-right: 50px;
            padding-top: 14px;
            align-items: center;
        }

        .checkout-style .credit-card-maestro-icon {
            position: absolute;
            top: 0;
            right: 0;
            display: flex;
            padding-right: 90px;
            padding-top: 14px;
            align-items: center;
        }

        .checkout-style #creditCardTypeIcon {
            height: 40%;
            width: auto;
            position: absolute;
            display: flex;
            right: 0;
            top: 0;
            bottom: 0;
            margin: auto 1rem auto auto;
        }

        .checkout-style #creditCardTypeSecondIcon {
            height: 40%;
            width: auto;
            position: absolute;
            display: flex;
            right: 0;
            top: 0;
            bottom: 0;
            margin: auto 4rem auto auto;
        }

        .checkout-style #selectCardLabel {
            position: absolute;
            right: 0;
            bottom: 0;
            margin: 0 2rem 2px 0;
            font-size: 10px;
            opacity: 0.7;
        }

        .checkout-style .pensio_payment_form_cvc-info-text {
            font-size: 10px;
            line-height: normal;
            margin-top: 4px;
            margin-bottom: 4px;
        }

        .checkout-style .pensio_payment_form_label_cell {
            font-size: 14px;
        }

        .checkout-style .cardholdername_row,
        .checkout-style .expiry_row,
        .checkout-style .cardnumber_row {
            margin-top: 0;
        }

        .checkout-style .cardnumber_row {
            margin-bottom: 20px;
        }

        .checkout-style .expiry_row {
            display: flex;
            width: 100%;
            gap: 0 10px;
        }

        .checkout-style .submit_row {
            margin-top: 20px;
        }

        .checkout-style .AltaPaySubmitButton,
        .checkout-style #submitbutton,
        .checkout-style #cancelPayment,
        .checkout-style input[type="button"] {
            outline: none;
            padding: 15px 16px;
            color: white;
            border-radius: 3px;
            width: 100%;
            border: none;
            cursor: pointer;
            box-shadow: rgba(0, 0, 0, 0.16) 0 1px 4px;
            font-weight: bold;
            font-size: 17px;
        }

        .checkout-style .AltaPaySubmitButton,
        .checkout-style input[type="button"] {
            background: #31C37E;
        }

        .checkout-style .AltaPaySubmitButton:hover,
        .checkout-style input[type="button"]:hover {
            background: #16b36e;
        }

        .checkout-style .AltaPaySubmitButton:disabled,
        .checkout-style #submitbutton,
        .checkout-style #cancelPayment,
        .checkout-style #submitbutton:disabled {
            background: black;
        }

        /*errors*/
        .checkout-style .pensio_required_field_indicator,
        .checkout-style #invalid_amex_cvc,
        .checkout-style #invalid_cvc,
        .checkout-style #invalid_cardholdername,
        .checkout-style #invalid_expire_month,
        .checkout-style #invalid_expire_year {
            color: red;
            font-size: 12px;
            line-height: normal;
        }

        .checkout-style .pensio_payment_form_invalid-cvc-input,
        .checkout-style .pensio_payment_form_invalid-cardholder-input {
            color: red;
        }

        .checkout-style .PensioCloseButton,
        .checkout-style .CustomAltaPayCloseButton {
            width: 40px;
            height: 20px;
            font-size: 18px;
            background-color: red;
            color: white;
            cursor: pointer;
            padding: 4px;
            position: absolute;
            right: 0;
            top: 0;
        }

        .checkout-style .PensioRadioButton {
            border: none;
            background-color: transparent;
            cursor: pointer;
        }

        .checkout-style div.PensioMultiformContainer form {
            display: none;
        }

        .checkout-style #PensioJavascriptDisabledSurchargeNotice {
            color: red;
            background-color: white;
        }

        .checkout-style #iDealPayment table {
            width: 100%;
        }

        .checkout-style #iDealPayment table tr td {
            vertical-align: middle;
            display: block;
        }

        .checkout-style #iDealPayment #pensioPaymentIdealSubmitButton {
            margin-top: 20px;
        }

        .checkout-style #idealIssuer select {
            color: #666;
        }

        .checkout-style select#birthdateDay,
        .checkout-style select#birthdateMonth,
        .checkout-style input#cancelPayment,
        .checkout-style input#enableAccount,
        .checkout-style input#acceptTerms,
        .checkout-style input#phoneNumber {
            margin-bottom: 10px;
        }

        .checkout-style div.PensioMultiformContainer form {
            position: relative;
            border: none;
            background-color: white;
            padding: 0;
            margin: 0;
            border-radius: 0;
            top: 0;
            width: 100%;
        }

        .checkout-style input#CreditCardButton {
            left: 0px;
        }

        .checkout-style input#GiftCardButton {
            left: 100px;
        }

        .checkout-style div.PensioMultiformContainer .FormTypeButton {
            position: absolute;
            top: -32px;
            height: 32px;
            margin-left: 25px;
            border: 1px solid rgba(0, 0, 0, 0.16);
        }

        .checkout-style div.PensioMultiformContainer {
            position: initial;
        }

        .checkout-style .pensio_payment_form_input_cell.pensio_payment_form_card-number {
            position: relative;
        }

        .checkout-style .content-wrapper {
            align-items: center;
            max-width: 680px;
            padding: 0;
            box-sizing: border-box;
            flex: 1;
            display: flex;
            margin-top: 0;
            background: transparent;
            justify-content: center;
            flex-direction: column;
            width: 100%;
        }

        span.secure-payments-text {
            font-size: 10px !important;
            text-align: right;
            display: block;
            margin-top: 8px;
        }

        body.checkout-style {
            height: 100vh;
            display: flex;
            flex-direction: column;
            background-color: #dddddd;
        }

        .checkout-style .header-minimal {
            background-color: #ffffff;
        }

        .checkout-style #pensioCreditCardPaymentSubmitButton,
        .checkout-style #submitbutton,
        .checkout-style #MobilePaymentFormSubmit {
            border-radius: 3px;
            padding: 15px;
            width: 100%;
            border: none;
        }

        .checkout-v2 #pensioCreditCardPaymentSubmitButton,
        .checkout-v2 #submitbutton,
        .checkout-v2 #MobilePaymentFormSubmit {
            padding: 16px;
        }

        .checkout-style #pensioCreditCardPaymentSubmitButton:disabled,
        .checkout-style #MobilePaymentFormSubmit:disabled {
            background-color: black;
        }

        .checkout-v2 .pensio_payment_form_row {
            margin-bottom: 0;
        }

        .checkout-v2 .pensio_payment_form-date {
            cursor: pointer;
            display: flex;
            align-items: center;
            font-family: monospace !important;
            border: 1px solid rgba(0, 0, 0, 0.16);
            border-top: 0;
            border-bottom-left-radius: 4px;
            border-bottom-right-radius: 0px;
        }

        .checkout-v2 .separator {
            color: #a9a9ac;
        }

        .checkout-v2 .pensio_payment_form_year {
            width: 25%;
        }

        .checkout-v2 .pensio_payment_form_card-number input,
        .checkout-v2 .expire-month, .checkout-v2 #emonth,
        .checkout-v2 .expiry-year,
        .checkout-v2 .pensio_payment_form-cvc-input input#cvcInput,
        .checkout-v2 .pensio_payment_form_cardholder input {
            cursor: pointer;
            height: 52px;
            font-size: 16px;
            color: #666;
        }

        .checkout-v2 .pensio_payment_form_card-number input {
            padding: 16px 14px;
            width: 100%;
            box-sizing: border-box;
            border-radius: 4px;
            border: 1px solid rgba(0, 0, 0, 0.16);
            border-bottom-left-radius: 0;
            border-bottom-right-radius: 0;
            outline: none;
            box-shadow: none;
        }

        .checkout-v2 .pensio_payment_form_card-number,
        .checkout-v2 .pensio_payment_form_cardholder,
        .checkout-v2 .pensio_payment_form-cvc-input input#cvcInput {
            margin-top: 0 !important;
        }

        .checkout-v2 .pensio_payment_form_cardholder input {
            outline: none;
            box-shadow: none;
        }

        .checkout-v2 .pensio_payment_form-cvc-input input#cvcInput {
            padding: 16px 14px;
            height: 53px;
            box-sizing: border-box;
            width: 100%;
            border-bottom: 1px solid rgba(0, 0, 0, 0.16) !important;
            border-right: 1px solid rgba(0, 0, 0, 0.16) !important;
            border-radius: 4px;
            border-top: 0;
            border-left: none;
            border-bottom-left-radius: 0;
            border-top-right-radius: 0;
            outline: none;
            color: #666;
            box-shadow: none;
        }

        .checkout-v2 .expire-month, .checkout-v2 #emonth {
            padding-top: 16px;
            padding-bottom: 16px;
            padding-left: 2px !important;
            margin: auto 4px auto 14px;
            font-family: monospace !important;
            width: 100%;
            border: none;
            outline: none;
            box-shadow: none !important;
            box-sizing: border-box;
        }

        .checkout-v2 .expiry-year {
            padding: 16px 4px;
            width: 100%;
            border: none;
            outline: none;
            font-family: monospace !important;
            box-sizing: border-box;
        }

        .checkout-v2 .pensio_payment_form_month {
            width: 20%;
            max-width: 40px;
        }

        .checkout-v2 .pensio_payment_form_cvc {
            width: 50%;
        }

        .checkout-v2 .pensio_payment_form_row.expiry_row {
            float: none;
            margin-top: 0;
            gap: 0;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .checkout-v2 .secure-payments-text {
            width: 100%;
            position: relative;
            float: left;
            text-align: right;
            font-size: 10px;
            padding-top: 5px;
        }

        .checkout-style .payment-form-wrapper {
            background-color: white;
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 600px;
        }

        .checkout-v2 div.payment-form-wrapper {
            padding: 35px 30px !important;
            display: inline-block;
        }

        .checkout-v2 .pensio_payment_form_cvc,
        .checkout-v2 .pensio_payment_form_date-container {
            width: 50%;
        }

        .checkout-style .info-wrapper {
            margin-bottom: 25px;
        }

        .checkout-v2 .surcharge-amount,
        .altapay-back-to-shop {
            display: none;
        }

        input#phoneNumber {
            margin-bottom: 15px;
        }

        #Mobile table tr td {
            display: block;
        }

        form#Mobile {
            margin-bottom: 15px;
        }

        .checkout-style input#MobilePaymentFormSubmit {
            color: #ffffff;
        }

        .checkout-style #iDealPayment table,
        .checkout-style #Mobile table {
            width: 100%;
        }

        .checkout-style div#paymentFormWaiting {
            text-align: center;
            padding: 10px 0;
        }

        .pensio_required_field_indicator,
        #invalid_amex_cvc,
        #invalid_cvc,
        #invalid_cardholdername,
        #invalid_cardholderemail,
        #invalid_expire_month,
        #invalid_expire_year,
        #invalid_cardnumber_length {
            color: #810303;
            font-size: 12px;
            margin-top: 4px;
        }

        @media screen and (max-width: 768px) {
            .checkout-style .content-wrapper {
                max-width: 100%;
                flex-direction: unset;
            }

            .checkout-style div.payment-form-wrapper {
                border-radius: 0;
                padding: 0 30px 0 30px !important;
                max-width: 100%;
                width: 100%;
            }
        }
    </style>
</head>
<body class="{$stylingclass}">
<header class="header-minimal">
    <div class="header-minimal-logo">
        <img src="{$shop_logo}" alt="logo" class="header-logo-main-img">
    </div>
</header>
<div class="content-wrapper">
    <div class="payment-form-wrapper">
        <form id="PensioPaymentForm">
            <!--All content in here will be replaced by the actual payment form-->
        </form>
    </div>
</div>
</body>
</html>