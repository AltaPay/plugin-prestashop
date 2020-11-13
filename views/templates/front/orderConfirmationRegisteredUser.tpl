<!doctype html>

{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

<html lang="{$language.iso_code}">

<head>
    {block name='head'}
        {include file='_partials/head.tpl'}
    {/block}
</head>

<body id="{$page.page_name}" class="{$page.body_classes|classnames}">

{hook h='displayAfterBodyOpeningTag'}

<main>

    <header id="header">
        {block name='header'}
            {include file='_partials/header.tpl'}
        {/block}
    </header>
    <section id="wrapper">
        <div class="container">

            {block name='breadcrumb'}
                {include file='_partials/breadcrumb.tpl'}
            {/block}

            {block name="content_wrapper"}
                <h1>
                    {l s='Order Details' mod='altapay'}
                </h1>
                <br>
                <div id="content-wrapper">
                    {block name="content"}
                        <section id="content" style="box-shadow: 2px 2px 8px 0 rgba(0,0,0,.2); background: #fff;" class="page-content">
                            <div class="container">
                                <section class="page_content">
                                    <div class="col-xs-12" style="padding-top: 5%">
                                        <h3>{l s="Your order has been placed successfully"}</h3>
                                        <br>
                                        <table class="table table-striped table-bordered table-labeled hidden-xs-down">
                                            <thead class="thead-default">
                                            <tr>
                                                <th>Date</th>
                                                <th>Status</th>
                                                {if $paymentNature === 'CreditCard' && empty($creditCardStatus)}
                                                <th>Credit card</th>
                                                {/if}
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <td><br><p>{$orderDetails->date_add|date_format:"%d/%m/%Y"}</p></td>
                                                <td><span class="label label-pill dark" style="background-color:#32CD32">{l s='Payment accepted'}</span></td>
                                                {if $paymentNature === 'CreditCard' && empty($creditCardStatus)}
                                                    <td>
                                                            <a class="btn btn-primary light-button btn-sm hidden-xs-down" style="text-transform: none; font-weight: 600; padding: 0.35rem 0.375rem;"
                                                                    type="submit" href="{$link->getModuleLink('altapay', 'savecreditcard', ['orderID'=>$orderID, 'cardMask'=>$cardMask, 'cardToken'=>$cardToken, 'cardBrand'=>$cardBrand, 'cardExpiryDate'=>$cardExpiryDate ])}" name="savecreditcardDetails" value="Save credit card details">Save your credit card for later use</a>
                                                    </td>
                                                {/if}

                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <div class="col-xs-6">
                                            <p></p>
                                            <table class="table table-bordered">
                                                <thead class="thead-default">
                                                <tr>
                                                    <th>{l s='Delivery Address'}</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <tr>
                                                    <td>
                                                        <p>{$deliveryAddress->firstname} {$deliveryAddress->lastname}</p>
                                                        <p>{$deliveryAddress->address1}</p>
                                                        <p>{$deliveryAddress->postcode} {$deliveryAddress->city}</p>
                                                        <p>{$deliveryAddress->country}</p>
                                                        <p>{$deliveryAddress->phone}</p>
                                                    </td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="col-xs-6">
                                            <p></p>
                                            <table class="table table-bordered">
                                                <thead class="thead-default">
                                                <tr>
                                                    <th>{l s='Invoice Address'}</th>
                                                </tr>
                                                </thead>
                                                <tbody>
                                                <tr>
                                                    <td>
                                                        <p>{$invoiceAddress->firstname} {$invoiceAddress->lastname}</p>
                                                        <p>{$invoiceAddress->address1}</p>
                                                        <p>{$invoiceAddress->postcode} {$invoiceAddress->city}</p>
                                                        <p>{$invoiceAddress->country}</p>
                                                        <p>{$invoiceAddress->phone}</p>
                                                    </td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    <div class="col-xs-12" style="display: inline-block">
                                        <p></p>
                                        <table id="order-products" class="table table-bordered">
                                            <thead class="thead-default">
                                            <tr>
                                                <th>{l s='Reference'}</th>
                                                <th>{l s='Product'}</th>
                                                <th>{l s='Quantity'}</th>
                                                <th>{l s='Unit price'}</th>
                                                <th>{l s='Total price'}</th>
                                            </tr>
                                            </thead>
                                            <tfoot>
                                            <tr class="item">
                                                <td colspan="4"><p
                                                            style="float: right">{l s="Subtotal"}</p>
                                                </td>
                                                <td colspan="1"><p>{number_format($orderDetails->total_products_wt,2,'.','')}</p></td>
                                            </tr>
                                            <tr class="item">
                                                <td colspan="4"><p style="float: right">{l s="Discounts"}</p>
                                                </td>
                                                <td colspan="1"><p>{number_format($orderDetails->total_discounts,2,'.','')}</p></td>
                                            </tr>
                                            <tr class="item">
                                                <td colspan="4"><p
                                                            style="float: right">{l s="Shipping and handling"}</p>
                                                </td>
                                                <td colspan="1"><p>{number_format($orderDetails->total_shipping,2,'.','')}</p></td>
                                            </tr>
                                            <tr class="item">
                                                <td colspan="4"><p
                                                            style="float: right">{l s="Total"}</p></td>
                                                <td colspan="1"><p>{number_format($orderDetails->total_paid,2,'.','')}</p></td>
                                            </tr>
                                            </tfoot>
                                            <tbody>
                                            {foreach $productDetails as $product}
                                                <tr class="ap-orderlines-capture">
                                                    <td><p>{$product.reference}</p></td>
                                                    <td><p><a>{$product.product_name}</a></p></td>
                                                    <td> <p>{$product.product_quantity}</p> </td>
                                                    <td> <p>{number_format($product.unit_price_tax_incl,2,'.','')}</p> </td>
                                                    <td> <p>{number_format($product.total_price_tax_incl,2,'.','')}</p></td>
                                                </tr>
                                            {/foreach}
                                            </tbody>
                                        </table>
                                        <p></p>
                                    </div>
                                </section>
                            </div>
                        </section>
                    {/block}
                    <br>
                </div>
            {/block}

        </div>
    </section>
    <br>
    <?php
        if (array_key_exists('delete', $_POST)) {
            deleteRecord();
        }
        function deleteRecord()
        {
            echo 'Done';
        }
    ?>
    <footer id="footer">
        {block name="footer"}
            {include file="_partials/footer.tpl"}
        {/block}
    </footer>

</main>

{hook h='displayBeforeBodyClosingTag'}

{block name='javascript_bottom'}
    {include file="_partials/javascript.tpl" javascript=$javascript.bottom}
{/block}

</body>

</html>