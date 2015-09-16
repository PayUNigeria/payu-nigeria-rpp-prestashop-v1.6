{*
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
*  @author Kenneth Onah <kenneth@netcraft-devops.com>
*  @copyright  2015 NetCraft DevOps
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  Property of NetCraft DevOps
*}

{capture name=path}{l s='Credit/Debit card payment.' mod='payu'}{/capture}

<!--<div style="margin: 0 10px 10px 50px">-->
	<h3>{l s='Transaction error encountered while processing payment' mod='payu'}</h3>
	<p>
		<h4>{l s='Payment details ' mod='payu'}</h4>
		-{l s=' Amount: ' mod='payu'} <span class="price"> <strong>R{$total_paid}</strong></span>
		<br /><br />
		- {l s='Card: ' mod='payu'}  <strong>{if $cardInfo}{$cardInfo}{else}___________{/if}</strong>
		<br /><br />
		- {l s='Name on card: ' mod='payu'}  <strong>{if $name_on_card}{$name_on_card}{else}___________{/if}</strong>
		<br /><br />
		- {l s='Card Number: ' mod='payu'}  <strong>{if $card_number}{$card_number}{else}___________{/if}</strong>
		<br /><br />
		- {l s='PayU Reference: ' mod='payu'}  <strong>{if $payu_ref}{$payu_ref}{else}___________{/if}</strong>

		<br /><br />
		{l s='If you have any questions or concerns, please contact our ' mod='payu'} 
		<a href="{$link->getPageLink('contact', true)|escape:'html'}" style="color:#317fd8">{l s='CUSTOMER CARE.' mod='bankwire'}</a>
	</p>
<!--</div>-->
