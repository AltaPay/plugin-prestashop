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
    <section id="content">
        <div class="row">
            <div class="page-order-detail">
                <div class="cart-grid-body col-xs-12 col-lg-12">
                    <div id="PensioRedirectForm"></div>
                </div>
            </div>
        </div>
    </section>
{/block}

{block name='footer'}
    {include file='_partials/footer.tpl'}
{/block}