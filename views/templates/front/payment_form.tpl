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

<div id="{$stylingclass}">
<div id="card_info" {if ($cssClass)} class = "cvv_less" {/if}>
    {if $stylingclass == "checkout-cc"}
        <p class="payment-headline">{l s='You are about to pay' mod='altapay'} <strong><span id="PensioTotal">{$cart_summary.total_price} </span> {$currency_code}</strong>  {l s='for the order.' mod='altapay'}</p>
    {else}
        <p class="payment_msg">{l s="Please enter your details below" mod="altapay"}</p>
    {/if}
    <form id="PensioPaymentForm" ></form>
    <input type="button" class="btn btn-success PensioSubmitButton customPayButton" disabled="disabled" value="{l s='Confirm' mod='altapay'}" style="display:none;">
</div>
</div>
{include file = "$cart_info_path"}

