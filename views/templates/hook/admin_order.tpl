{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}
<div class="row" id="altapay">
    <div class="col-lg-12">
        <div class="panel">

            <div class="panel-heading">
                <img src="{$this_path}/logo.png" height="14" width="14">
                Payment information (AltaPay)
                {if !empty($altapay_module_update) }
                    <p style="float: right;">
                        <span style="border-radius: 8px; font-weight: normal;border: solid 2px #fbbb22;padding: 5px 15px;">
                            A new version <b><a target="_blank" href="{$altapay_module_update['link']}">{$altapay_module_update['version']}</a></b> of <b>AltaPay for PrestaShop</b> is available.
                        </span>
                    </p>
                {/if}
            </div>

            <div class="row panel-body" style="padding-top: 0;">

                {if isset($ap_error)}
                    <div class="alert alert-danger">{$ap_error}</div>
                {/if}

                <!-- Actions -->
                <div class="col-lg-12">
                    <div class="row row-ap">
                        <div class="col-lg-12">
                            <div class="table-responsive">
                                {if $ap_product_details}
                                <table class="table table-bordered">
                                    <tr style="font-weight: bold">
                                        <td>
                                            ID
                                        </td>
                                        <td>
                                            Description
                                        </td>
                                        <td>
                                            Order ID
                                        </td>
                                        <td>
                                            Attribute ID
                                        </td>
                                        <td>
                                            Price with tax
                                        </td>
                                        <td>
                                            Price without tax
                                        </td>
                                        <td>
                                            Ordered
                                        </td>
                                        <td>
                                            Captured
                                        </td>
                                        <td>
                                            Refunded
                                        </td>
                                        <td>
                                            Quantity
                                        </td>
                                        <td>
                                            Discount
                                        </td>
                                        <td>
                                            Total amount
                                        </td>
                                        {foreach $ap_product_details as $key => $value }
                                        {if !empty($ap_coupon_discount) && $ap_order_detail > 0}
                                            {foreach $ap_coupon_discount as $discount}
                                                {assign var="productID" value=$discount['productID']}
                                                {if $productID == $value['product_id']}
                                                    {assign var="discountPercent" value=$discount['discountPercent']}
                                                {/if}
                                                {if $discount['shipping']}
                                                    {assign var="freeShipping" value=1}
                                                {/if}
                                            {/foreach}
                                        {elseif $value['reduction_percent'] > 0 && $ap_order_detail > 0}
                                            {assign var="catalogRule" value="applied"}
                                            {assign var="discountPercent" value=$value['reduction_percent']}
                                        {else}
                                            {assign var="discountPercent" value=0}
                                        {/if}
                                    <tr class="ap-orderlines">
                                        {assign var="amount_without_tax" value=($value['unit_price_tax_excl'] * $value['product_quantity']) + ($value['unit_price_tax_incl']-$value['unit_price_tax_excl'])*$value['product_quantity']}
                                        {if {$value['product_attribute_id']} }
                                            <td class="ap-uniqueId">{$value['product_reference']}
                                                -{$value['product_attribute_id']}</td>
                                        {else}
                                            <td class="ap-uniqueId">{$value['product_reference']}</td>
                                        {/if}
                                        <td>{$value['product_name']}</td>
                                        <td><input type="text" name="ap_order_id" class="form-control fixed-width-xs"
                                                   value="{$ap_order_id}" style="border:none;" readonly/></td>
                                        <td><input type="text" name="ap_attribute_id[{$value['product_attribute_id']}]"
                                                   class="form-control fixed-width-xs"
                                                   value="{$value['product_attribute_id']}" style="border:none;"
                                                   readonly/></td>
                                        <td class="ap-orderline-unit-price">{round($value['unit_price_tax_incl'], 2)}</td>
                                        <td>{round($value['unit_price_tax_excl'], 2)}</td>
                                        <td class="ap-orderline-max-quantity">{$value['product_quantity']}</td>
                                        <td>{$ap_orders[$value['product_id']]['captured']}</td>
                                        <td>{$ap_orders[$value['product_id']]['refunded']}</td>
                                        <td><input type="number" name="ap_order_qty[{$value['uniqueId']}]"
                                                   class="form-control fixed-width-xs ap-order-modify" value="0"/></td>
                                        {if $discountPercent > 0}
                                            <td><input type="number" name="ap_coupon_discount"
                                                       class="form-control fixed-width-xs" value="{$discountPercent}"
                                                       style="border:none;" readonly/></td>
                                        {else}
                                            <td><input type="number" name="ap_coupon_discount"
                                                       class="form-control fixed-width-xs" value="0"
                                                       style="border:none;" readonly/></td>
                                        {/if}
                                        {if ($catalogRule)}
                                            {assign var="product_amount" value=number_format($amount_without_tax,2,'.','')}
                                        {else}
                                            {assign var="product_amount" value=number_format($amount_without_tax,2,'.','')-(number_format($amount_without_tax,2,'.','')*($discountPercent/100))}
                                        {/if}
                                        {if preg_match('/\.\d{3,}/', $product_amount)}
                                            {if substr($product_amount, -1) <= 5}
                                                <td class="ap-total-amount">{number_format(intval(((number_format($amount_without_tax,2,'.','')-(number_format($amount_without_tax,2,'.','')*($discountPercent/100)))*100))/100,2,'.','')}</td>
                                            {else}
                                                {assign var="total_product_amount" value=number_format($product_amount,2,'.','')}
                                                <td class="ap-total-amount">{$total_product_amount}</td>
                                            {/if}
                                        {else}
                                            {assign var="total_product_amount" value=number_format($product_amount,2,'.','')}
                                            <td class="ap-total-amount">{$total_product_amount}</td>
                                        {/if}

                                    </tr>
                                    {/foreach}

                                    {if $ap_gift_wrapping}
                                        <tr class="ap-orderlines">
                                            <td class="ap-uniqueId">giftwrap</td>
                                            <td>Gift Wrap</td>
                                            <td></td>
                                            <td></td>
                                            <td class="ap-orderline-unit-price">{$ap_gift_wrapping}</td>
                                            <td></td>
                                            <td class="ap-orderline-max-quantity">1</td>
                                            <td>0</td>
                                            <td>0</td>
                                            <td>
                                                <input type="number" name="ap_order_wrap"
                                                       class="form-control fixed-width-xs ap-order-modify" value="0"/>
                                            </td>
                                            <td class="ap-coupon-discount"></td>
                                            <td class="ap-total-amount">{$ap_gift_wrapping}</td>
                                        </tr>

                                        {/if}

                                    {foreach $ap_shipping_details as $key => $value }
                                        <tr class="ap-orderlines">
                                            <td class="ap-uniqueId">{$value['id_carrier']}</td>
                                            <td>{$value['carrier_name']}</td>
                                            <td><input type="text" name="ap_order_id"
                                                       class="form-control fixed-width-xs"
                                                       value="{$ap_order_id}" style="border:none;"
                                                       readonly/></td>
                                            <td><input type="text" name="ap_attribute_id[{$value['id_order_invoice']}]"
                                                       class="form-control fixed-width-xs"
                                                       value="{$value['id_order_invoice']}" style="border:none;"
                                                       readonly/></td>
                                            <td class="ap-orderline-unit-price">{round($value['shipping_cost_tax_incl'], 2)}</td>
                                            <td>{round($value['shipping_cost_tax_excl'], 2)}</td>
                                            <td class="ap-orderline-max-quantity">1</td>
                                            <td>0</td>
                                            <td>0</td>
                                            <td><input type="number" name="ap_order_qty[{$value['uniqueId']}]"
                                                       class="form-control fixed-width-xs ap-order-modify" value="0"/>
                                            </td>
                                            <td class="ap-coupon-discount">
                                                {if $freeShipping}
                                                <input type="number"
                                                       name="ap_coupon_discount"
                                                       class="form-control fixed-width-xs"
                                                       value="100" style="border:none;"
                                                       readonly/>
                                                {else}
                                                <input type="number"
                                                                                  name="ap_coupon_discount"
                                                                                  class="form-control fixed-width-xs"
                                                                                  value="0" style="border:none;"
                                                                                  readonly/>
                                            {/if}
                                            </td>
                                            {if $freeShipping}
                                                <td class="ap-total-amount">0</td>
                                            {else}
                                                <td class="ap-total-amount">{number_format($value['shipping_cost_tax_incl'],2,'.','')}</td>
                                            {/if}
                                        </tr>
                                    {/foreach}
                                </table>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>

                {if $payment_id}
                <div class="col-sm-6" id="transactionOptions">
                    <div class="row row-ap">
                        <div class="col-lg-2">
                            <label for="allow-orderlines" class="form-check-label">
                                <input name="allow-orderlines" type="checkbox" id="ap-allow-orderlines" value="1"
                                       checked="checked">
                                Send orderlines
                            </label>
                        </div>
                        <div class="col-lg-6">
                            <label for="goodwill-refund class=" form-check-label">
                            <input name="goodwill-refund" type="checkbox" id="ap-goodwill-refund" value="1">
                            Enforce Good-Will refund (use for Klarna only)
                            </label>
                        </div>
                    </div>
                    <div class="row row-ap">
                        <div class="col-lg-3">
                            <input name="amount" type="text" id="capture-amount" class="input"
                                   value="{if !$payment_captured}{$payment_amount}{/if}" placeholder="Amount">
                        </div>
                        <div class="col-lg-3">
                            <a href="#" class="btn btn-primary" id="btn-capture" data-url="{$ajax_url}"
                               data-payment-id="{$payment_id}">Capture</a>
                        </div>
                    </div>

                    <div class="row row-ap">
                        <div class="col-lg-3">
                            <input name="amount" type="text" id="refund-amount" class="input" placeholder="Amount">
                        </div>
                        <div class="col-lg-3">
                            <a href="#" class="btn btn-primary" id="btn-refund" data-url="{$ajax_url}"
                               data-payment-id="{$payment_id}">Refund</a>
                        </div>
                    </div>
                    <div class="row row-ap">
                        <div class="col-lg-6">
                            <a href="#" class="btn btn-danger" id="btn-release" data-url="{$ajax_url}"
                               data-payment-id="{$payment_id}">Release payment</a>
                        </div>
                    </div>

                </div>
                <br>
                {/if}
                <div class="col-sm-12">
                    <div class="col-sm-6">
                        {if !$reserved_payment_id && $additional_amount}
                            <div class="row row-ap">
                                <div class="col-lg-12" style="padding: 0;">
                                    {if !$payment_url}
                                    <h4>Generate Payment Link</h4>
                                    <label style="display:block;">
                                        <span>Amount</span>
                                        <input id="order-additional-amount" class="form-control input" type="text"
                                                value="{round($additional_amount, 2)}" style="width: auto;"/>
                                    </label>
                                    {if !$payment_id}
                                        <label style="display: block;">
                                            <span style="display: block;">Terminal</span>
                                            <select class="custom-select" id="order-terminal"
                                                style="width:auto;height:38px;vertical-align:initial;">
                                                {foreach $terminals as $terminal}
                                                    {if $terminal['id'] == 'EmbraceIT Test Terminal'}
                                                        <option value="{$terminal['id']}" selected>{$terminal['name']}</option>
                                                    {else}
                                                        <option value="{$terminal['id']}">{$terminal['name']}</option>
                                                    {/if} 
                                                {/foreach}
                                            </select>
                                        </label>
                                    {/if}
                                    <label for="send-payment-link-email" style="display: block;">
                                        Send email?
                                        <input type="checkbox" id="send-payment-link-email" value="1" checked="checked">
                                    </label>
                                    <a href="#" class="btn btn-primary btn-ap" id="generate-payment-link-btn"
                                        data-orderid="{$id_order}" data-url="{$generate_payment_link_ajax_url}"
                                        style="text-align: left;width: auto;margin-bottom: 5px;">Generate Link</a>
                                    <div class="send-message"></div>
                                    {/if}
                                </div>
                                {if $payment_url}
                                    <div class="col-lg-12" style="padding: 0;">
                                        <p>
                                            <strong>Payment Link for additional amount: </strong> <a href="{$payment_url}">{$payment_url}</a>
                                        </p>
                                    </div>
                                {/if}
                            </div>
                        {/if}

                        {if $is_require_capture}
                            <div class="row row-ap">
                                <div class="col-lg-6" style="padding: 0;">
                                    <a href="#" class="btn btn-primary btn-ap" id="btn-remaining-capture"
                                       data-url="{$generate_payment_link_ajax_url}" data-orderid="{$id_order}"
                                       data-payment-id="{$reserved_payment_id}"
                                       data-remaining_amount="{$additional_amount_reserved}"
                                       style="text-align: left;width: auto;">Capture <strong>{$additional_amount_reserved}</strong> <small>(Payment Link)</small></a>
                                </div>
                            </div>
                        {/if}

                        {if $can_refund}
                            <div class="row row-ap">
                                <div class="col-lg-6" style="padding: 0;">
                                    <a href="#" class="btn btn-primary btn-ap" id="btn-remaining-refund"
                                       data-url="{$generate_payment_link_ajax_url}" data-orderid="{$id_order}"
                                       data-payment-id="{$reserved_payment_id}"
                                       data-remaining_amount="{$additional_amount_reserved}"
                                       style="text-align: left;width: auto;">Refund <strong>{$additional_amount_reserved}</strong> <small>(Payment Link)</small></a>
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>
                <div class="col-lg-12" style="margin-top:2%;">
                    <!-- Details -->
                    {if $ap_paymentinfo}
                    <div class="row row-ap">
                    <div class="col-lg-12">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tbody>
                                <tr style="font-weight: bold">
                                    <td>Status</td>
                                    <td>{$ap_paymentinfo['status']}</td>
                                </tr>
                                <tr>
                                    <td>Reserved amount</td>
                                    <td id="reservedAmount" value="{$ap_paymentinfo['reserved']}">{displayPrice price=$ap_paymentinfo['reserved'] currency=$currency->id}</td>
                                </tr>
                                <tr>
                                    <td>Captured amount</td>
                                    <td id="capturedAmount" value="{$ap_paymentinfo['captured']}">{displayPrice price=$ap_paymentinfo['captured'] currency=$currency->id}</td>
                                </tr>
                                <tr>
                                    <td>Refunded amount</td>
                                    <td id="refundedAmount" value="{$ap_paymentinfo['refunded']}">{displayPrice price=$ap_paymentinfo['refunded'] currency=$currency->id}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                {/if}
                <div class="row row-ap">
                    <div class="col-lg-12">
                    {if $paymentinfo}
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <tbody>
                            {foreach $paymentinfo as $key => $value}
                                <tr>
                                    <td>{$key}</td>
                                    <td class="{$key|replace:' ':'_'}" value="{$value}">{$value}</td>
                                </tr>
                            {/foreach}
                            </tbody>
                        </table>
                    </div>
                    {/if}
                        {if $child_order_id}
                            <h3 style="margin: 0;">Additional Payment via Payment Link</h3>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <tbody>
                                    <tr>
                                        <td>Transaction ID</td>
                                        <td>{$child_order_id}</td>
                                    </tr>
                                    <tr>
                                        <td>Payment ID</td>
                                        <td>{$reserved_payment_id}</td>
                                    </tr>
                                    <tr>
                                        <td>Amount</td>
                                        <td>{displayPrice price=$additional_amount currency=$currency->id}</td>
                                    </tr>
                                    <tr>
                                        <td>Amount Reserved</td>
                                        <td>{displayPrice price=$additional_amount_reserved currency=$currency->id}</td>
                                    </tr>
                                    <tr>
                                        <td>Amount Captured</td>
                                        <td>{displayPrice price=$child_order_captured currency=$currency->id}</td>
                                    </tr>
                                    </tbody>
                                </table>
                            </div>
                        {/if}
                </div>
            </div>

            {if count($reconciliation_identifiers) gt 0}
                <div class="row row-ap">
                    <div class="col-lg-12">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <tbody>
                                <tr>
                                    <td style="font-weight: bold">Reconciliation Identifiers</td>
                                    <td style="font-weight: bold">Types</td>
                                </tr>
                                {foreach $reconciliation_identifiers as $key => $reconciliation_identifier}
                                    <tr>
                                        <td>{$reconciliation_identifier['reconciliation_identifier']}</td>
                                        <td>{$reconciliation_identifier['transaction_type']}</td>
                                    </tr>
                                {/foreach}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            {/if}

        </div>

    </div>

</div>
</div>
</div>
{literal}

<script>
    $(document).ready(function(){
        $('#altapay').insertBefore($('#altapay').prev('div'));
        var value = $(".Payment_Type").text();
        var paymentStatus = $(".Payment_Status").text();
        if (paymentStatus === 'Deny' || paymentStatus === 'Payment Released') {
            $("#transactionOptions").hide();
        }
        var capturedAmount = parseFloat($("#capturedAmount").text());
        var reservedAmount = parseFloat($("#reservedAmount").text());
        var refundedAmount = parseFloat($("#refundedAmount").text());

        if(value === 'paymentAndCapture' || value === 'subscriptionAndCharge')
        {
            if(refundedAmount < reservedAmount) {
                $("#capture-amount").hide();
                $("#btn-capture").hide();
                $("#btn-release").hide();
            } else {
                $("#transactionOptions").hide();
            }

        } else {
            if(capturedAmount > 0 && capturedAmount < reservedAmount && refundedAmount < reservedAmount) {
                $("#btn-release").hide();
            } else if(capturedAmount > 0 && capturedAmount == reservedAmount && refundedAmount < reservedAmount){
                $("#btn-release").hide();
                $("#capture-amount").hide();
                $("#btn-capture").hide();
            } else if(capturedAmount > 0 && refundedAmount == reservedAmount){
                $("#transactionOptions").hide();
            }
        }


    });
</script>
{/literal}