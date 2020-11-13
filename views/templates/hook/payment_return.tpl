{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

{if $status === 'ok' || $status === 'open'}

	{if $status === 'ok'}
	<div class="alert alert-success">{l s='Your payment has been authorized' mod='altapay'}</div>
	{elseif $status === 'open'}
	<div class="alert alert-success">{l s='Your payment request has been received and is awaiting payment processing' mod='altapay'}</div>
	{/if}

	<table class="table table-condensed table-bordered">
		<tr>
			<td style="font-weight: bold">{l s='Total' mod='altapay'}:</td>
			<td>{$total_to_pay}</td>
		</tr>
		<tr>
			<td style="font-weight: bold">{l s='Payment transaction number' mod='altapay'}:</td>
			<td>{$payment_id}</td>
		</tr>
		<tr>
			<td style="font-weight: bold">{l s='Order number' mod='altapay'}:</td>
		</tr>
	</table>
{/if}