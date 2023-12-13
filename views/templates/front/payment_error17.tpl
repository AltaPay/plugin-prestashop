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
{/block}

{block name='content'}
    <section id="wrapper">
        <div id="payment_error">
            <p class="alert alert-warning">{l s='Error in payment process. Payment was not authorized' mod='altapay'}</p>
            <p>
                <a class="btn btn-default btn-primary"
                    href="{$link->getPageLink('order', true, NULL, "step=3")|escape:'html'}#altapay">{l s='Try again' mod='altapay'}</a>
            </p>
        </div>
    </section>
{/block}

{block name='footer'}
    {include file='_partials/footer.tpl'}
{/block}