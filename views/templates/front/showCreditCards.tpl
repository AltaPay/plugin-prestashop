{extends file='customer/page.tpl'}

{block name='page_title'}
  {l s='Saved credit card(s)' mod='altapay'}
{/block}

{block name='page_content'}
  <div class="row">
    <section id="content" style="box-shadow: 2px 2px 8px 0 rgba(0,0,0,.2); background: #fff; " class="page-content">
        {if $savedCreditCard}
        <table class="table" border="0" cellspacing="5" cellpadding="5">
            <tbody>
            <tr style="font-weight: bold; border-collapse: collapse; padding: 15px;">
                <td>{l s='Card type'}</td>
                <td>{l s='Masked pan'}</td>
                <td>{l s='Expires'}</td>
                <td>{l s='Action'}</td>
            </tr>
            </tbody>
            {foreach $savedCreditCard as $item}
            <tr>
                <td id="userCreditcardBrand" class="userCreditcardBrand" >{{$item.cardBrand}}</td>
                <td id="userCreditcard" class="userCreditcard" >{{$item.creditCard}}</td>
                <td id="userCreditcardExpiryDate" class="userCreditcardExpiryDate" >{{$item.cardExpiryDate}}</td>
                <td><a class="btn btn-warning light-button btn-sm hidden-xs-down"
                        type="submit" href="{$link->getModuleLink('altapay', 'deletecreditcard', ['customerID'=>$item.userID, 'creditCardNumber'=>$item.creditCard])}" name="deletecreditcard" value="deletecreditcard">{l s='Delete'}</a>
                </td>
            </tr>
            {/foreach}

        </table>
        {else}
            <p>{l s='No saved credit cards to display'}</p>
        {/if}
    </section>
  </div>
{/block}