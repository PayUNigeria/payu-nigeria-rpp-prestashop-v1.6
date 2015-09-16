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

{capture name=path}{l s='Credit/Debit card payment' mod='payu'}{/capture}

<p>
	<p>{l s='An error was encountered while processing your order ' mod='payu'}</p>
	{l s='Please contact our ' mod='payu'} 
	<a href="{$link->getPageLink('contact', true)|escape:'html'}" style="color:#317fd8">{l s='CUSTOMER CARE.' mod='payu'}</a>
</p>
