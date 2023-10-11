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
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <script type="text/javascript" src="{$module_dir}views/js/form.js"></script>
    <style>
        .loader {
            border: 8px solid #f3f3f3;
            border-radius: 50%;
            border-top: 8px solid #aaaaaa;
            width: 50px;
            height: 50px;
            -webkit-animation: spin 1s linear infinite;
            /* Safari */
            animation: spin 1s linear infinite;
            margin: 15px auto;
        }

        /* Safari */
        @-webkit-keyframes spin {
            0% {
                -webkit-transform: rotate(0deg);
            }

            100% {
                -webkit-transform: rotate(360deg);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
</style>
{/block}

{block name='content'}
    <section id="content">
        <div class="row">
            <div class="page-order-detail">
                <div class="cart-grid-body col-xs-12 col-lg-12" id="ajaxContent">
                    <script type="text/javascript">
                        var transaction_id = '{$transaction_id}';
                    </script>
                    <p style="margin: 30px 0;text-align: center;">
                        {l s='Payment is in Processing' mod='altapay'}
                    </p>
                    <div class="loader"></div>
                </div>
            </div>
        </div>
    </section>
    <script type="text/javascript">
        $(document).ready(function() {
            function checkResponse() {
                $.ajax({
                    url: '{$link->getModuleLink('altapay', 'checkorderstatus')}',
                    method: 'POST',
                    dataType: 'json',
                    data: { transaction_id: transaction_id },
                    success: function(response) {
                        if(response.success == true){
                            location.href = response.url;
                        }else{
                            console.log('not ok');
                        }
                        setTimeout(checkResponse, 2000);
                    },
                    error: function() {
                        setTimeout(checkResponse, 2000);
                    }
                });
            }

            checkResponse();
        });
    </script>

{/block}

{block name='footer'}
    {include file='checkout/_partials/footer.tpl'}
{/block}
