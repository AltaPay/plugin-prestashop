{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

{assign var="cart_info_path" value="`$smarty.current_dir`/cart_info.tpl"}
{assign var="cart_summary" value=$summarydetails}
{assign currency_code Currency::getDefaultCurrency()->sign}
<link rel="stylesheet" href="{$css_dir}/theme.css" type="text/css" />
</header>
{if ($theme_name) == "Niara"}
<style>
    #radio_pay_over_time, #radio_pay_later {
        opacity: 1;
        position: relative;
        vertical-align: top;
        margin-right: 5px;
    }
</style>
{/if}
<div id="{$stylingclass}">
<div id="card_info" {if ($cssClass)} class = "cvv_less" {/if}>
    {if ($stylingclass) != "checkout-cc"}
        <p class="payment_msg">{l s="Please enter your details below" mod="altapay"}</p>
    {/if}
    <form id="PensioPaymentForm" ></form>
    <input type="button" class="btn btn-success PensioSubmitButton customPayButton" disabled="disabled" value="{l s='Confirm' mod='altapay'}" style="display:none;">
</div>
</div>
{include file = "$cart_info_path"}

