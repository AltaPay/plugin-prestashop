{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}


<head>
   {block name='head'}
      {include file='_partials/head.tpl'}
   {/block}
</head>

<header id="header">
  {block name='header'}
    {block name='header_nav'}
      <nav class="header-nav">
        <div class="topnav">
          {if isset($fullwidth_hook.displayNav1) AND $fullwidth_hook.displayNav1 == 0}
          <div class="container">
          {/if}
            <div class="inner">{hook h='displayNav1'}</div>
          {if isset($fullwidth_hook.displayNav1) AND $fullwidth_hook.displayNav1 == 0}
          </div>
          {/if}
        </div>
        <div class="bottomnav">
          {if isset($fullwidth_hook.displayNav2) AND $fullwidth_hook.displayNav2 == 0}
            <div class="container">
          {/if}
            <div class="inner">{hook h='displayNav2'}</div>
          {if isset($fullwidth_hook.displayNav2) AND $fullwidth_hook.displayNav2 == 0}
            </div>
          {/if}
        </div>
      </nav>
    {/block}
    {assign var="cart_info_path" value="module:altapay/views/templates/front/cart_info17.tpl"}
    {assign var="cart_summary" value=$summarydetails}
    {assign currency_code Currency::getDefaultCurrency()->sign}
  {/block}
  <style>
    #pensioCreditCardPaymentSubmitButton {
        display: none;
    }
    input.PensioSubmitButton.customPayButton {
      background: black;
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
</style>
</header>

{block name='content'}
  <section id="content">
    <div class="row">
      <div class="page-order-detail">
          <div class="cart-grid-body col-xs-12 col-lg-12">
            {block name='checkout_process'}
                <div id="card_info" {if ($cssClass)} class = "cvv_less" {/if}>
                <h1 class="payment_msg">{l s='Du er ved at betale' mod='altapay'} {$cart_summary.total_price} {$currency_code}</h1>
                    <form id="PensioPaymentForm" ></form>
                    <input type="button" class="PensioSubmitButton customPayButton" disabled="disabled" value="{l s='Betale' mod='altapay'} {$cart_summary.total_price} {$currency_code}">
                </div>
                {include file = "$cart_info_path"}
            {/block}
          </div>
      </div>
    </div>
  </section>
{/block}

{block name='footer'}
	<div class="footer-bottom">
	 	{if isset($fullwidth_hook.displayFooterAfter) AND $fullwidth_hook.displayFooterAfter == 0}
	 		<div class="container">
	 	{/if}
	 		<div class="inner">{hook h='displayFooterAfter'}</div>
	 	{if isset($fullwidth_hook.displayFooterAfter) AND $fullwidth_hook.displayFooterAfter == 0}
	 		</div>
		{/if}
	</div>
{/block}