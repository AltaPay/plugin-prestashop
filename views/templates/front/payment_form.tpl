{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

{assign var="cart_info_path" value="`$smarty.current_dir`/cart_info.tpl"}
<link rel="stylesheet" href="{$css_dir}/theme.css" type="text/css" />
<style>
    div#card_info {
        width: 70%;
        margin: 0 auto;
        padding: 10px 20px;
        background: #2fb5d22b;
    }

    div#order-detail-content {
        width: 70%;
        margin: 0 auto;
        padding: 10px 20px 30px;
        background: #2fb5d22b;
        margin-bottom: 40px;
    }

    .table-bordered, .table-bordered td, .table-bordered th {
        border: 2px solid #2fb5d2;
    }

    .table thead th {
        border-bottom: 3px solid #2fb5d2;
    }
    .pensio_payment_form_row {
        display: inline-block;
        padding-right: 70px;
        text-align: left;
    }

    .pensio_payment_form_outer {
        text-align: center;
    }

    div#card_info {
        text-align: center;
        margin: 40px auto;
    }
</style>
</header>
<div id="card_info"><p class="payment_msg">{l s="Please enter your details below" mod="altapay"}</p>
<form id="PensioPaymentForm" ></form>
    <input type="button" class="btn btn-success PensioSubmitButton customPayButton" disabled="disabled" value="{l s='Confirm' mod='altapay'}" style="display:none;">

</div>
{include file = "$cart_info_path"}

