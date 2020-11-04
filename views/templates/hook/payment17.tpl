{**
 * AltaPay module for PrestaShop
 *
 * Copyright © 2020 AltaPay. All rights reserved.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
*}

{if !empty($ccTokenControl) && $customerID}
    <select id="selectCreditCard" class="selectCreditCard">
        <option value="">{l s='Select a saved credit card'}</option>
        {foreach $savedCreditCard as $item}
            {if !empty($item.cardName)}
                <option value="{{$item.creditCard}}">{{$item.cardName}}</option>
            {else}
                <option value="{{$item.creditCard}}">{{$item.creditCard}} ({{$item.cardExpiryDate}})</option>
            {/if}

        {/foreach}
    </select>
{/if}

{if isset($smarty.get.altapay_unavailable)}
    <a id="altapay_unavailable" name="altapay_unavailable"></a>
    <div class="altapay_unavailable">{l s='Payment service temporary unavailable' mod='altapay'}</div>{/if}
