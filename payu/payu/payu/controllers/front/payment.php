<?php
/**
 * Prestashop PayU Plugin
 *
 * @category   Modules
 * @package    PayU
 * @copyright  Copyright (c) 2015 Netcraft Devops (Pty) Limited
 *             http://www.netcraft-devops.com
 * @author     Kenneth Onah <kenneth@netcraft-devops.com>
 */

//define('PS_OS_AWAITING_PAYMENT', 21);

require_once(dirname(__FILE__).'/../../payu.php');

class PayuPaymentModuleFrontController extends ModuleFrontController
{
	/*
	 * @see FrontController::postProcess()
	*/
	public function postProcess()
	{
		if(!isset($_GET['PayUReference']))
		{
			$cart = $this->context->cart;
			if (!$cart->id_customer || !$cart->id_address_delivery || !$cart->id_address_invoice|| !$this->module->active)
				Tools::redirect('index.php?controller=order&step=1');
			
			// Check that this payment option is still available in case the customer changed 
			// his address just before the end of the checkout process
			$authorized = false;
			foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'payu')
			{
				$authorized = true;
				break;
			}
			if (!$authorized) {
				//die($this->module->l('This payment method is not available.', 'payment'));
				Tools::redirect('index.php?controller=order&step=1');
			}
			$customer = new Customer($cart->id_customer);
			if (!Validate::isLoadedObject($customer))
				Tools::redirect('index.php?controller=order&step=1');
			
			$currency = $this->context->currency;
			$total = (float)$cart->getOrderTotal(true, Cart::BOTH);
			
			//$this->module->validateOrder($cart->id, Configuration::get('PS_OS_AWAITING_PAYMENT'), $total, $this->module->displayName, NULL, NULL, (int)$currency->id, false, $customer->secure_key);
			$this->module->confirmationUrl();
		}
	}
}
