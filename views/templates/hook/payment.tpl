{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

{if isset($smarty.get.altapay_unavailable)}<a id="altapay_unavailable" name="altapay_unavailable"></a><div class="altapay_unavailable">{l s='Payment service temporary unavailable' mod='altapay'}</div>{/if}
{foreach $methods as $m}
<div class="row" id="altapay">
	<div class="col-xs-12">
		<p class="payment_module">
			<a class="altapay" href="{$link->getModuleLink('altapay', 'payment', ['method'=> $m.id_terminal])|escape:'html'}" title="{$m.display_name}" style="background: url({$base_dir_ssl}modules/altapay/views/img/payment_icons/{$m.icon_filename}) 15px 15px no-repeat #fbfbfb">
				{$m.display_name}
			</a>
		</p>
	</div>
</div>
{/foreach}
