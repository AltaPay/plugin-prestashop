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
    {/block}
</header>

{block name='content'}
    <section id="content">
        <div class="row">
            <div class="page-order-redirection">
                <div class="cart-grid-body col-xs-12 col-lg-12">
                    {block name='checkout_process'}
                        <div id="PensioRedirectForm"></div>
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