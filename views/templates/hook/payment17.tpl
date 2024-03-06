{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}
<span id="applepay-terminalid">{{$applepayTerminalId}}</span>
{if !empty($ccTokenControl) && $customerID}
<div class="savecard-checkbox">
    <label for="savecard">
        <input type="checkbox" name="savecard" class="savecard" id="savecard" />
        Save this card for future transactions
    </label>
</div>
<select name="ccToken" id="selectCreditCard" class="selectCreditCard">
    <option value="">{l s='Select a saved credit card'}</option>
    {foreach $savedCreditCard as $item}
            <option value="{{$item.id}}">{{$item.creditCard}} ({{$item.cardExpiryDate}})</option>

    {/foreach}
</select>
{/if}

{if isset($smarty.get.altapay_unavailable)}
    <a id="altapay_unavailable" name="altapay_unavailable"></a>
    <div class="altapay_unavailable">{l s='Payment service temporary unavailable' mod='altapay'}</div>
{/if}

<p {if !empty($ccTokenControl) && $customerID} style="padding-top: 10px;" {/if} >{$custom_message}</p>
