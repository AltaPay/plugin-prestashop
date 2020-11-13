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
                    {l s='Saved credit card(s)' mod='altapay'}
                </h1>
                <br>
                <div id="content-wrapper">
                    {block name="content"}
                        <section id="content" style="box-shadow: 2px 2px 8px 0 rgba(0,0,0,.2); background: #fff; " class="page-content">
                            {if $savedCreditCard}
                            <table class="table" border="0" cellspacing="5" cellpadding="5">
                                <tbody>
                                <tr style="font-weight: bold; border-collapse: collapse; padding: 15px;">
                                    <td>{l s='Card type'}</td>
                                    <td>{l s='Masked pan'}</td>
                                    <td>{l s='Expires'}</td>
{*                                    <td>{l s='Card name'}</td>*}
                                    <td>{l s='Action'}</td>

                                </tr>
                                </tbody>
                                {foreach $savedCreditCard as $item}
                                <tr>
                                    <td id="userCreditcardBrand" class="userCreditcardBrand" >{{$item.cardBrand}}</td>
                                    <td id="userCreditcard" class="userCreditcard" >{{$item.creditCard}}</td>
                                    <td id="userCreditcardExpiryDate" class="userCreditcardExpiryDate" >{{$item.cardExpiryDate}}</td>
                                    <td><a class="btn btn-warning light-button btn-sm hidden-xs-down"
                                           type="submit" href="{$link->getModuleLink('altapay', 'deletecreditcard', ['customerID'=>$item.userID, 'creditCardNumber'=>$item.creditCard])}" name="deletecreditcard" value="deletecreditcard">{l s='Delete'}</a>
                                    </td>
                                </tr>
                                {/foreach}

                            </table>
                                <input hidden type="text" id="updateLink" value="{$link->getModuleLink('altapay', 'updatecreditcard')}">
                            {else}
                                <p>{l s='No saved credit cards to display'}</p>
                            {/if}
                        </section>

                    {/block}
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
        {literal}
            <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
            <script>
                $(document).ready(function () {
                    $(".cardName").keyup(function () {
                        var row = $(this).closest("tr");
                        var index = row.index();
                        document.getElementsByClassName("msg")[index].innerHTML = ("Press enter to save the card name").fontcolor("red");
                    });
                    $(".cardName").change(function () {
                        var row = $(this).closest("tr");    // Find the row
                        var userCreditcard = row.find(".userCreditcard").text(); // Find the text
                        var userID = parseInt(row.find(".userID").text()); // Find the text
                        let updatelink = $("#updateLink").val();
                        var cardName = $(this).val();
                        $.ajax({
                            type: "POST",
                            url: updatelink,
                            data: {
                                "userID": userID,
                                "userCreditcard": "" + userCreditcard + "",
                                "cardName": cardName
                            },
                            success: function (response) {
                                var index = row.index();
                                document.getElementsByClassName("msg")[index].innerHTML = ("Changes saved".fontcolor("green"));
                            }
                        });
                    });
                });
            </script>
        {/literal}
    </footer>

</main>

{hook h='displayBeforeBodyClosingTag'}

{block name='javascript_bottom'}
    {include file="_partials/javascript.tpl" javascript=$javascript.bottom}
{/block}

</body>

</html>