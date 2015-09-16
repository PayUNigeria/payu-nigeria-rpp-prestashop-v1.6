<?php
/**
 * 2007-2014 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 *  @author Kenneth Onah <kenneth@netcraft-devops.com>
 *  @copyright  2015 NetCraft DevOps
 *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  Property of NetCraft DevOps
 */

class PayuValidationModuleFrontController extends ModuleFrontController
{
	/*
	 * @see FrontController::postProcess()
	*/
	public function postProcess()
	{
		//$reference = $_GET['PayUReference'];
		$reference = Tools::getValue('PayUReference');
		$returnData = $this->module->getSoapTransaction($reference);
		$cart = $this->context->cart;
		$currency = $this->context->currency;
		$customer = new Customer($cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			die('Invalid customer ID');

		if((isset($reference) && $reference) && (isset($returnData) && $returnData))
		{
			if(is_array($returnData) && count($returnData) > 0 && isset($returnData['return']) && is_array($returnData['return']))
			{
				$returnCode = (isset($returnData['return']['resultCode']) && $returnData['return']['resultCode'] != '') ? $returnData['return']['resultCode'] : 1;
				$transaction_state = (isset($returnData['return']['transactionState']) && $returnData['return']['transactionState'] != '') ? $returnData['return']['transactionState'] : '';
					
				switch($returnCode)
				{
					case '05':
						// Failed Payment
						//$cancelUrl = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'index.php?controller=order';
						$this->context->smarty->assign(array(
								'hide_left_column' => $this->display_column_left,
								'total_paid' => Tools::ps_round(Tools::convertPrice($returnData['return']['paymentMethodsUsed']['amountInCents'] / 100, $currency->id, false), 2),
								'cardInfo' => $returnData['return']['paymentMethodsUsed']['information'],
								'name_on_card' => $returnData['return']['paymentMethodsUsed']['nameOnCard'],
								'card_number' => $returnData['return']['paymentMethodsUsed']['cardNumber'],
								'state' => $returnData['return']['transactionState'],
								'payu_ref' => $returnData['return']['payUReference'],
						));
						return $this->setTemplate('failed.tpl');
						break;
							
					case '301':
						// Cancel Payment
						$cancelUrl = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'index.php?controller=order&submitReorder=&id_order='.$params['objOrder']->id;
						Tools::redirect($cancelUrl);	
						break;
							
					case '00':
						//Successfull Payment
						$total = 0;
						$total_to_pay = (float)$cart->getOrderTotal(true, Cart::BOTH);
						//$amount_paid = (float)($returnData['return']['paymentMethodsUsed']['amountInCents'] / 100);
						$amount_paid = Tools::ps_round(Tools::convertPrice($returnData['return']['paymentMethodsUsed']['amountInCents'] / 100, $currency->id, false), 2);
						$mailVars = array(
							'transaction_id' => $returnData['return']['payUReference'],
						);
						
						if($transaction_state === 'SUCCESSFUL')
						{
							if($total_to_pay == $amount_paid)
							{
								$total = $total_to_pay;
							}
							else
							{
								$total = $amount_paid;
							}

							$this->module->validateOrder($cart->id, Configuration::get('PS_OS_PAYMENT'), $total, $this->module->displayName, NULL, $mailVars, (int)$currency->id, false, $customer->secure_key);
							$this->display_column_left = true;
							$this->context->smarty->assign(array(
								'hide_left_column' => $this->display_column_left,
								'total_paid' => Tools::ps_round(Tools::convertPrice($returnData['return']['paymentMethodsUsed']['amountInCents'] / 100, $currency->id, false), 2),
								'cardInfo' => $returnData['return']['paymentMethodsUsed']['information'],
								'name_on_card' => $returnData['return']['paymentMethodsUsed']['nameOnCard'],
								'card_number' => $returnData['return']['paymentMethodsUsed']['cardNumber'],
								'state' => $returnData['return']['transactionState'],
								'payu_ref' => $returnData['return']['payUReference'],
							));
							return $this->setTemplate('confirmation.tpl');
						}
						break;

					default:
						$this->context->smarty->assign(array(
							'hide_left_column' => $this->display_column_left,
							'total_paid' => Tools::ps_round(Tools::convertPrice($returnData['return']['paymentMethodsUsed']['amountInCents'] / 100, $currency->id, false), 2),
							'cardInfo' => $returnData['return']['paymentMethodsUsed']['information'],
							'name_on_card' => $returnData['return']['paymentMethodsUsed']['nameOnCard'],
							'card_number' => $returnData['return']['paymentMethodsUsed']['cardNumber'],
							'state' => $returnData['return']['transactionState'],
							'payu_ref' => $returnData['return']['payUReference'],
						));
						return $this->setTemplate('failed.tpl');
						break;
				}
			}
		}
		return $this->setTemplate('cancel.tpl');
	}
}
