{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

{assign var="cart_summary" value=$summarydetails}
{assign var="cart_products" value=$products}
 {assign currency Currency::getDefaultCurrency()->sign}
    {assign currency_code Currency::getDefaultCurrency()->iso_code}
<div id="order-detail-content" class="table_block table-responsive">
    <table id="cart_summary" class="table table-bordered">
        <thead>
        <tr>
            <th class="cart_product first_item">{l s='Product' mod='altapay'}</th>
            <th class="cart_description item">{l s='Description' mod='altapay'}</th>
           {if isset($PS_STOCK_MANAGEMENT) && $PS_STOCK_MANAGEMENT}
                <th class="cart_availability item text-center">{l s='Availability' mod='altapay'}</th>
            {/if}
            <th class="cart_unit item text-right">{l s='Unit price' mod='altapay'}</th>
            <th class="cart_quantity item text-center">{l s='Qty' mod='altapay'}</th>
            <th class="cart_total last_item text-right">{l s='Total' mod='altapay'}</th>
        </tr>
        </thead>
        <tfoot>
        {if $cart_summary.total_tax}
            {if $cart_summary.total_price}
                <tr class="cart_total_price">
                    <td colspan="4" class="text-right">{l s='Total products (tax excl.)' mod='altapay'}</td>
                    <td colspan="2" class="price" id="total_product">{$currency_code}{$cart_summary.total_products}</td>
                </tr>
            {else}
                <tr class="cart_total_price">
                    <td colspan="4" class="text-right">{l s='Total products (tax incl.)' mod='altapay'}</td>
                    <td colspan="2" class="price" id="total_product">{$currency_code}{$cart_summary.total_products_wt}</td>
                </tr>
            {/if}
        {else}
            <tr class="cart_total_price">
                <td colspan="4" class="text-right">{l s='Total products' mod='altapay'}</td>
                <td colspan="2" class="price" id="total_product">{$currency_code}{$cart_summary.total_products}</td>
            </tr>
        {/if}
        <tr class="cart_total_voucher" {if $cart_summary.total_wrapping == 0}style="display:none"{/if}>
            <td colspan="4" class="text-right">
                {if $cart_summary.total_tax}
                    {if $cart_summary.total_price}
                        {if $cart_summary.total_wrapping == 0}{l s='Total gift wrapping (tax excluded.):' mod='altapay'}{else}{l s='Total gift wrapping (tax included.):' mod='altapay'}{/if}
                    {/if}
                {else}
                    {l s='Total gift wrapping cost:' mod='altapay'}
                {/if}
            </td>
            <td colspan="2" class="price-discount price" id="total_wrapping">
                {if $cart_summary.total_tax}
                    {if $cart_summary.total_price}
                       {$currency_code} {$cart_summary.total_wrapping_tax_exc}
                    {else}
                        {$cart_summary.total_wrapping}
                    {/if}
                {else}
                    {$cart_summary.total_wrapping_tax_exc}
                {/if}
            </td>
        </tr>
        {if $cart_summary.total_shipping_tax_exc <= 0 && (!isset($isVirtualCart) || !$isVirtualCart) && $cart_summary.free_ship}
            <tr class="cart_total_delivery">
                <td colspan="4" class="text-right">{l s='Total shipping' mod='altapay'}</td>
                <td colspan="2" class="price" id="total_shipping">{l s='Free Shipping!' mod='altapay'}</td>
            </tr>
        {else}
            {if  $cart_summary.total_tax && $cart_summary.total_shipping_tax_exc != $cart_summary.total_shipping}
                {if $cart_summary.total_price}
                    <tr class="cart_total_delivery" {if $cart_summary.total_shipping <= 0} style="display:none"{/if}>
                        <td colspan="4" class="text-right">{if $display_tax_label}{l s='Total shipping (tax excl.)' mod='altapay'}{else}{l s='Total shipping' mod='altapay'}{/if}</td>
                        <td colspan="2" class="price" id="total_shipping">{$currency_code}{$cart_summary.total_shipping_tax_exc}</td>
                    </tr>
                {else}
                    <tr class="cart_total_delivery"{if $cart_summary.total_shipping <= 0} style="display:none"{/if}>
                        <td colspan="4" class="text-right">{if $display_tax_label}{l s='Total shipping (tax incl.)' mod='altapay'}{else}{l s='Total shipping' mod='altapay'}{/if}</td>
                        <td colspan="2" class="price" id="total_shipping" >{$currency_code}{$cart_summary.total_shipping}</td>
                    </tr>
                {/if}
            {else}
                <tr class="cart_total_delivery"{if $cart_summary.total_shipping <= 0} style="display:none"{/if}>
                    <td colspan="4" class="text-right">{l s='Total shipping' mod='altapay'}</td>
                    <td colspan="2" class="price" id="total_shipping" > {$currency_code}{$cart_summary.total_shipping_tax_exc}</td>
                </tr>
            {/if}
        {/if}
        <tr class="cart_total_voucher" {if $cart_summary.total_discounts == 0}style="display:none"{/if}>
            <td colspan="4" class="text-right">

            </td>
            <td colspan="2" class="price-discount price" id="total_discount">
                {if $cart_summary.total_tax}
                    {if $cart_summary.total_price}
                       {$currency_code} {$cart_summary.total_discounts_tax_exc*-1}
                    {else}
                       {$currency_code} {$cart_summary.total_discounts*-1}
                    {/if}
                {else}
                    {$currency_code}{$cart_summary.total_discounts_tax_exc*-1}
                {/if}
            </td>
        </tr>
        {if  $cart_summary.total_tax}
            {if $cart_summary.total_tax != 0 && isset($show_taxes)}
                {if $cart_summary.total_price != 0}
                    <tr class="cart_total_price">
                        <td colspan="4" class="text-right">{if $display_tax_label}{l s='Total (tax excl.)' mod='altapay'}{else}{l s='Total' mod='altapay'}{/if}</td>
                        <td colspan="2" class="price" id="total_price_without_tax"> {$currency_code} {$cart_summary.total_price_without_tax}</td>
                    </tr>
                {/if}
                <tr class="cart_total_tax">
                    <td colspan="4" class="text-right">{l s='Tax' mod='altapay'}</td>
                    <td colspan="2" class="price" id="total_tax" >{$currency_code}{$cart_summary.total_tax}</td>
                </tr>
            {/if}
            <tr class="cart_total_price">
                <td colspan="4" class="total_price_container text-right"><span>{l s='Total' mod='altapay'}</span></td>
                <td colspan="2" class="price" id="total_price_container">
                    <span id="total_price" data-selenium-total-price="{$cart_summary.total_price}"> {$currency_code} {$cart_summary.total_price}</span>
                </td>
            </tr>
        {else}
            <tr class="cart_total_price">
                {if isset($voucherAllowed)}
                    <td colspan="2" id="cart_voucher" class="cart_voucher">
                        <div id="cart_voucher" class="table_block">
                            {if isset($voucherAllowed)}
                                <form action="{if $opc}{$link->getPageLink('order-opc', true)}{else}{$link->getPageLink('order', true)}{/if}" method="post" id="voucher">
                                    <fieldset>
                                        <h4>{l s='Vouchers' mod='altapay'}</h4>
                                        <input type="text" id="discount_name" class="form-control" name="discount_name" value="{if isset($discount_name) && $discount_name}{$discount_name}{/if}" />
                                        <input type="hidden" name="submitDiscount" />
                                        <button type="submit" name="submitAddDiscount" class="button btn btn-default button-small"><span>{l s='ok' mod='altapay'}</span></button>
                                        {if $displayVouchers}
                                            <p id="title" class="title_offers">{l s='Take advantage of our offers:' mod='altapay'}</p>
                                            <div id="display_cart_vouchers">
                                                {foreach from=$displayVouchers item=voucher}
                                                    <span onclick="$('#discount_name').val('{$voucher.name}');return false;" class="voucher_name">{$voucher.name}</span> - {$voucher.description} <br />
                                                {/foreach}
                                            </div>
                                        {/if}
                                    </fieldset>
                                </form>
                            {/if}
                        </div>
                    </td>
                {/if}
                <td colspan="{if !isset($voucherAllowed)}4{else}2{/if}" class="text-right total_price_container">
                    <span>{l s='Total' mod='altapay'}</span>
                </td>
                <td colspan="2" class="price total_price_container" id="total_price_container">
                    <span id="total_price" data-selenium-total-price="{$total_price_without_tax}">{$currency_code}{$total_price_without_tax}</span>
                </td>
            </tr>
        {/if}
        </tfoot>

        <tbody>
        {foreach from=$cart_products item=product name=productLoop}
            {assign var='productId' value=$product.id_product}
            {assign var='productAttributeId' value=$product.id_product_attribute}
            {assign var='quantityDisplayed' value=0}
            {assign var='cannotModify' value=1}
            {assign var='odd' value=$product@iteration%2}
            {assign var='noDeleteButton' value=1}

            <tr id="product_{$product.id_product}_{$product.id_product_attribute}_{if $quantityDisplayed > 0}nocustom{else}0{/if}_{$product.id_address_delivery|intval}{if !empty($product.gift)}_gift{/if}" class="cart_item{if isset($productLast) && $productLast && (!isset($ignoreProductLast) || !$ignoreProductLast)} last_item{/if}{if isset($productFirst) && $productFirst} first_item{/if}{if isset($customizedDatas.$productId.$productAttributeId) AND $quantityDisplayed == 0} alternate_item{/if} address_{$product.id_address_delivery|intval} {if $odd}odd{else}even{/if}">
                <td class="cart_product">
                    <a href="{$link->getProductLink($product.id_product, $product.link_rewrite, $product.category, null, null, $product.id_shop, $product.id_product_attribute, false, false, true)|escape:'html':'UTF-8'}"><img src="{$link->getImageLink($product.link_rewrite, $product.id_image, 'small_default')|escape:'html':'UTF-8'}" alt="{$product.name|escape:'html':'UTF-8'}" {if isset($smallSize)}width="{$smallSize.width}" height="{$smallSize.height}" {/if} /></a>
                </td>
                <td class="cart_description">
                    <p class="product-name"><a href="{$link->getProductLink($product.id_product, $product.link_rewrite, $product.category, null, null, $product.id_shop, $product.id_product_attribute, false, false, true)|escape:'html':'UTF-8'}">{$product.name|escape:'html':'UTF-8'}</a></p>
                    {if $product.reference}<small class="cart_ref">{l s='SKU' mod='altapay'}{$smarty.capture.default}{$product.reference|escape:'html':'UTF-8'}</small>{/if}
                    {if isset($product.attributes) && $product.attributes}<small><a href="{$link->getProductLink($product.id_product, $product.link_rewrite, $product.category, null, null, $product.id_shop, $product.id_product_attribute, false, false, true)|escape:'html':'UTF-8'}">{$product.attributes|@replace: $smarty.capture.sep:$smarty.capture.default|escape:'html':'UTF-8'}</a></small>{/if}
                </td>
               {if isset($PS_STOCK_MANAGEMENT) && $PS_STOCK_MANAGEMENT}
                    <td class="cart_avail"><span class="label{if $product.quantity_available <= 0 && isset($product.allow_oosp) && !$product.allow_oosp} label-danger{elseif $product.quantity_available <= 0} label-warning{else} label-success{/if}">{if $product.quantity_available <= 0}{if isset($product.allow_oosp) && $product.allow_oosp}{if isset($product.available_later) && $product.available_later}{$product.available_later}{else}{l s='In Stock' mod='altapay'}{/if}{else}{l s='Out of stock' mod='altapay'}{/if}{else}{if isset($product.available_now) && $product.available_now}{$product.available_now}{else}{l s='In Stock' mod='altapay'}{/if}{/if}</span>{if !$product.is_virtual}{hook h="displayProductDeliveryTime" product=$product}{/if}</td>
                {/if}
                <td class="cart_unit" data-title="{l s='Unit price' mod='altapay'}">
                    <ul class="price text-right" id="product_price_{$product.id_product}_{$product.id_product_attribute}{if $quantityDisplayed > 0}_nocustom{/if}_{$product.id_address_delivery|intval}{if !empty($product.gift)}_gift{/if}">
                        {if !empty($product.gift)}
                            <li class="gift-icon">{l s='Gift!' mod='altapay'}</li>

                        {/if}
                    </ul>
                </td>

                <td class="cart_quantity text-center" data-title="{l s='Quantity' mod='altapay'}">
                    {if (isset($cannotModify) && $cannotModify == 1)}
                        <span>
				{if $quantityDisplayed == 0 AND isset($customizedDatas.$productId.$productAttributeId)}
                    {$product.customizationQuantityTotal}
                {else}
                    {$product.cart_quantity-$quantityDisplayed}
                {/if}
			</span>
                    {else}
                        {if isset($customizedDatas.$productId.$productAttributeId) AND $quantityDisplayed == 0}
                            <span id="cart_quantity_custom_{$product.id_product}_{$product.id_product_attribute}_{$product.id_address_delivery|intval}" >{$product.customizationQuantityTotal}</span>
                        {/if}
                        {if !isset($customizedDatas.$productId.$productAttributeId) OR $quantityDisplayed > 0}

                            <input type="hidden" value="{if $quantityDisplayed == 0 AND isset($customizedDatas.$productId.$productAttributeId)}{$customizedDatas.$productId.$productAttributeId|@count}{else}{$product.cart_quantity-$quantityDisplayed}{/if}" name="quantity_{$product.id_product}_{$product.id_product_attribute}_{if $quantityDisplayed > 0}nocustom{else}0{/if}_{$product.id_address_delivery|intval}_hidden" />
                            <input size="2" type="text" autocomplete="off" class="cart_quantity_input form-control grey" value="{if $quantityDisplayed == 0 AND isset($customizedDatas.$productId.$productAttributeId)}{$customizedDatas.$productId.$productAttributeId|@count}{else}{$product.cart_quantity-$quantityDisplayed}{/if}"  name="quantity_{$product.id_product}_{$product.id_product_attribute}_{if $quantityDisplayed > 0}nocustom{else}0{/if}_{$product.id_address_delivery|intval}" />
                            <div class="cart_quantity_button clearfix">
                                {if $product.minimal_quantity < ($product.cart_quantity-$quantityDisplayed) OR $product.minimal_quantity <= 1}
                                    <a rel="nofollow" class="cart_quantity_down btn btn-default button-minus" id="cart_quantity_down_{$product.id_product}_{$product.id_product_attribute}_{if $quantityDisplayed > 0}nocustom{else}0{/if}_{$product.id_address_delivery|intval}" href="{$link->getPageLink('cart', true, NULL, "add=1&amp;id_product={$product.id_product|intval}&amp;ipa={$product.id_product_attribute|intval}&amp;id_address_delivery={$product.id_address_delivery|intval}&amp;op=down&amp;token={$token_cart}")|escape:'html':'UTF-8'}" title="{l s='Subtract' mod='altapay'}">
                                        <span><i class="icon-minus"></i></span>
                                    </a>
                                {else}
                                    <a class="cart_quantity_down btn btn-default button-minus disabled" href="#" id="cart_quantity_down_{$product.id_product}_{$product.id_product_attribute}_{if $quantityDisplayed > 0}nocustom{else}0{/if}_{$product.id_address_delivery|intval}" title="{l s='You must purchase a minimum of %d of this product.' mod='altapay' sprintf=$product.minimal_quantity}">
                                        <span><i class="icon-minus"></i></span>
                                    </a>
                                {/if}
                                <a rel="nofollow" class="cart_quantity_up btn btn-default button-plus" id="cart_quantity_up_{$product.id_product}_{$product.id_product_attribute}_{if $quantityDisplayed > 0}nocustom{else}0{/if}_{$product.id_address_delivery|intval}" href="{$link->getPageLink('cart', true, NULL, "add=1&amp;id_product={$product.id_product|intval}&amp;ipa={$product.id_product_attribute|intval}&amp;id_address_delivery={$product.id_address_delivery|intval}&amp;token={$token_cart}")|escape:'html':'UTF-8'}" title="{l s='Add' mod='altapay'}"><span><i class="icon-plus"></i></span></a>
                            </div>
                        {/if}
                    {/if}
                </td>

                {if !isset($noDeleteButton) || !$noDeleteButton}
                    <td class="cart_delete text-center" data-title="{l s='Delete' mod='altapay'}">
                        {if (!isset($customizedDatas.$productId.$productAttributeId) OR $quantityDisplayed > 0) && empty($product.gift)}
                            <div>
                                <a rel="nofollow" title="{l s='Delete' mod='altapay'}" class="cart_quantity_delete" id="{$product.id_product}_{$product.id_product_attribute}_{if $quantityDisplayed > 0}nocustom{else}0{/if}_{$product.id_address_delivery|intval}" href="{$link->getPageLink('cart', true, NULL, "delete=1&amp;id_product={$product.id_product|intval}&amp;ipa={$product.id_product_attribute|intval}&amp;id_address_delivery={$product.id_address_delivery|intval}&amp;token={$token_cart}")|escape:'html':'UTF-8'}"><i class="icon-trash"></i></a>
                            </div>
                        {else}

                        {/if}
                    </td>
                {/if}
                <td class="cart_total" data-title="{l s='Total' mod='altapay'}">
		<span class="price" id="total_product_price_{$product.id_product}_{$product.id_product_attribute}{if $quantityDisplayed > 0}_nocustom{/if}_{$product.id_address_delivery|intval}{if !empty($product.gift)}_gift{/if}">
			{if !empty($product.gift)}
                <span class="gift-icon">{l s='Gift!' mod='altapay'}</span>
            {else}
                {if $quantityDisplayed == 0 AND isset($customizedDatas.$productId.$productAttributeId)}
                    {if !isset($priceDisplay)}{$product.total_customization_wt}{else}{$product.total_customization}{/if}
                {else}
                    {if !isset($priceDisplay)}{$product.total_wt}{else}{$product.total}{/if}
                {/if}
            {/if}
		</span>
                    {hook h='displayCartExtraProductActions' product=$product}
                </td>

            </tr>

            {if isset($customizedDatas.$productId.$productAttributeId)}
                {foreach from=$customizedDatas.$productId.$productAttributeId[$product.id_address_delivery] key='id_customization' item='customization'}
                    <tr id="product_{$product.id_product}_{$product.id_product_attribute}_{$id_customization}" class="alternate_item cart_item">
                        <td colspan="4">
                            {foreach from=$customization.datas key='type' item='datas'}
                                {if $type == $CUSTOMIZE_FILE}
                                    <div class="customizationUploaded">
                                        <ul class="customizationUploaded">
                                            {foreach from=$datas item='picture'}
                                                <li>
                                                    <img src="{$cart_summary.image_dir}{$picture.value}_small" alt="" class="customizationUploaded" />
                                                </li>
                                            {/foreach}
                                        </ul>
                                    </div>
                                {elseif $type == $CUSTOMIZE_TEXTFIELD}
                                    <ul class="typedText">
                                        {foreach from=$datas item='textField' name='typedText'}
                                            <li>
                                                {if $textField.name}
                                                    {l s='%s:' mod='altapay' sprintf=$textField.name}
                                                {else}
                                                    {l s='Text #%s:' mod='altapay' sprintf=$smarty.foreach.typedText.index+1}
                                                {/if}
                                                {$textField.value}
                                            </li>
                                        {/foreach}
                                    </ul>
                                {/if}
                            {/foreach}
                        </td>
                        <td class="cart_quantity text-center">
                            {$customization.quantity}
                        </td>
                        <td class="cart_total"></td>
                    </tr>
                    {assign var='quantityDisplayed' value=$quantityDisplayed+$customization.quantity}
                {/foreach}
                {* If it exists also some uncustomized products *}
                {if $product.quantity-$quantityDisplayed > 0}{include file="checkout/_partials/cart-detailed-product-line.tpl"}{/if}
            {/if}

        {/foreach}
        </tbody>
    </table>
</div> <!-- end order-detail-content -->