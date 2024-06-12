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
              <div id="{$stylingclass}">
                <div id="card_info" {if ($cssClass)} class = "cvv_less" {/if}>
                    {if ($stylingclass) != "checkout-cc"}
                      <h1 class="payment_msg">{l s="Please enter your details below" mod="altapay"}</h1>
                    {/if}
                    <form id="PensioPaymentForm" ></form>
                    <input type="button" class="btn btn-success PensioSubmitButton customPayButton" disabled="disabled" value="{l s='Confirm' mod='altapay'}" style="display:none;">
                </div>
              </div>
                {if isset($amount)}
                    <table id="cart_summary" class="table table-bordered">
                        <tr class="cart_total_price">
                            <td colspan="4" class="total_price_container text-right"><span>Remaining Total</span></td>
                            <td colspan="2" class="price" id="total_price_container">
                                <span id="total_price"> {$currency_code}{$amount}</span>
                            </td>
                        </tr>
                    </table>
                {else}
                    {include file="$cart_info_path"}
                {/if}
            {/block}
          </div>
      </div>
    </div>
  </section>
{/block}

{block name='footer'}
  {include file='checkout/_partials/footer.tpl'}
{/block}