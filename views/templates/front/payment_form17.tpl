{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}


{extends file=$layout}
{block name='header'}
  {include file='checkout/_partials/header.tpl'}
   <link rel="stylesheet" href="{$css_dir}/theme.css" type="text/css" />
  {assign var="cart_info_path" value="module:altapay/views/templates/front/cart_info17.tpl"}
  {assign var="cart_summary" value=$summarydetails}
  {assign currency_code Currency::getDefaultCurrency()->sign}
{/block}

{block name='content'}
  <section id="content">
    <div class="row">
      <div class="page-order-detail">
          <div class="cart-grid-body col-xs-12 col-lg-12">
            {block name='checkout_process'}
                <div id="card_info" {if ($cssClass)} class = "cvv_less" {/if}>
                    <h1 class="payment_msg">{l s='You are about to pay' mod='altapay'} {$cart_summary.total_price} {$currency_code}</h1>
                    <form id="PensioPaymentForm" ></form>
                    <input type="button" class="btn btn-success PensioSubmitButton customPayButton" disabled="disabled" value="{l s='Confirm' mod='altapay'}" style="display:none;">
                </div>
                {include file = "$cart_info_path"}
            {/block}
          </div>
      </div>
    </div>
  </section>
{/block}

{block name='footer'}
  {include file='checkout/_partials/footer.tpl'}
{/block}





