{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

<div class="panel">
	<h1>{l s="AltaPay: Payments less complicated" mod="altapay"}</h1>
    {if !empty($altapay_module_update) }
		<div class="alert alert-message" style="margin-top: 15px;margin-bottom: 0px;">
			A new version <b><a href="{$altapay_module_update['link']}">{$altapay_module_update['version']}</a></b> of <b>AltaPay for PrestaShop</b> is available. We recommend updating your module to the latest version.
		</div>
    {/if}
	<br>
	<div class="alert alert-info">
		<h4>{l s="How to use this module" mod="altapay"}</h4>
		<ol>
			<li>{l s="Enter merchant details (username, password, etc.) below" mod="altapay"}</li>
			<li>{l s="Add your payment methods (credit card, PayPal, etc.) in the Terminals panel" mod="altapay"}</li>
		</ol>
		<p>{l s="Once done you're ready to accept payments and start selling." mod="altapay"}</p>
		<br>
		<h4>{l s='Additional Configurations:'} <span style="font-size: initial; font-weight: bold;">{l s ='(If you are using Subscription Products module by Webkul.)'}</span></h4>
		<ul>
			<li>
				{l s='Please make sure the curl library is installed on your server to execute the cron tasks.' mod='altapay'}
			</li>
			<li>
				<strong>{l s='Please remove their cron entry and instead insert the following line in your cron tasks manager for creating and scheduling automatic subscription orders and processing recurring payments.' mod='altapay'}</strong>
			</li>
			<li>
				<code>23 45 * * * curl {$altapay_recurring_payments_cron_link}</code>
			</li>
		</ul>
	</div>
</div>