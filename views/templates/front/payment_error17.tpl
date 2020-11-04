{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

{include file="_partials/head.tpl"}
<link rel="stylesheet" href="{$css_dir}/theme.css" type="text/css" />
<style>
div#payment_error{
width:50%;
}
</style>
<header id="header">
    {include file="_partials/header.tpl"}
</header>
<div id="payment_error">
<p class="alert alert-warning">
{l s='Error in payment process. Payment was not authorized' mod='altapay'}
</p>
<p><a class="btn btn-default" href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}#altapay">{l s='Try again' mod='altapay'}</a></p>
</div>
{include file="_partials/footer.tpl"}