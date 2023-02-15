{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

<div class="panel">
	<h1>{l s="AltaPay: Payments less complicated" mod="altapay"}</h1>
	<br>
	<div class="alert alert-info">
		<h4>{l s="How to use this module" mod="altapay"}</h4>
		<ol>
			<li>{l s="Enter merchant details (username, password, etc.) below" mod="altapay"}</li>
			<li>{l s="Add your payment methods (credit card, PayPal, etc.) in the Terminals panel" mod="altapay"}</li>
		</ol>
		<p>{l s="Once done you're ready to accept payments and start selling." mod="altapay"}</p>
		<br>
		<h4>{l s='Additional Configurations:'} <span style="font-size: initial;">{l s ='(If you are using Subscription Products module by Webkul.)'}</span></h4>
		<ul>
			<li>
				{l s='Please make sure the curl library is installed on your server to execute the cron tasks.' mod='altapay'}
			</li>
			<li>
				{l s='For processing recurring payments, please insert the following line in your cron tasks manager for every night at 03:00 AM:' mod='altapay'}
			</li>
			<li>
				<code>0 3 * * * curl {$altapay_recurring_payments_cron_link}</code>
			</li>
		</ul>
	</div>
</div>