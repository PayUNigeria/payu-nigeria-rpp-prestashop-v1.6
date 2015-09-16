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
 
if(!defined('_PS_VERSION_'))
	exit;

class payu extends PaymentModule
{
	private	$_html = '';
	private $_postErrors = array();
	public static $soapClient;

	public function __construct()
	{
		$this->name = 'payu';
		$this->tab = 'payments_gateways';
		$this->version = '2.0.0';
		$this->author = 'Kenneth Onah';
		$this->author_uri = 'http://www.netcraft-devops.com';
		$this->currencies = true;
		$this->currencies_mode = 'radio';
		$this->compatibility = '1.6.';
		$this->limited_countries = array('ZA');

		if (!extension_loaded('soap'))
			$this->warning = $this->l('SOAP extension must be enabled on your server to use this module.');
		
		$this->bootstrap = true;
		parent::__construct();

		$this->displayName = $this->l('PayU');
		$this->description = $this->l('Accepts payments by PayU South Africa');
		$this->confirmUninstall = $this->l('Are you sure you want to delete your details ?');
		if (!count(Currency::checkPaymentCurrencies($this->id)))
			$this->warning = $this->l('No currency has been set for this module.');
	}

	public function install()
	{
		if (!parent::install()
		OR !$this->installCurrency()
		OR !$this->installOrderState()
		OR !Configuration::updateValue('PAYU_MERCHANT_REF', '7')
		OR !Configuration::updateValue('PAYU_SAFE_KEY', '{CE62CE80-0EFD-4035-87C1-8824C5C46E7F}')
		OR !Configuration::updateValue('PAYU_SOAP_USERNAME', '100032')
		OR !Configuration::updateValue('PAYU_SOAP_PASSWORD', 'PypWWegU')
		OR !Configuration::updateValue('PAYU_3DS_ENABLED', 0)
		OR !Configuration::updateValue('PAYU_SHOW_BUDGET', 0)
		OR !Configuration::updateValue('PAYU_PAYMENT_METHODS', 'CREDITCARD')
		OR !Configuration::updateValue('PAYU_CURRENCY', 'ZAR')
		OR !Configuration::updateValue('PAYU_SERVER_MODE', 0)
		OR !Configuration::updateValue('PAYU_TRANSACTION_TYPE', 'PAYMENT')
		OR !Configuration::updateValue('PAYU_REDIRECT_URL', '')
		OR !Configuration::updateValue('PAYU_BASKET_DESC', '3D Sim Store FAuth Off Force On')
		OR !$this->registerHook('payment'))
			return false;
		return true;
	}

	public function uninstall()
	{
		if (!Configuration::deleteByName('PAYU_MERCHANT_REF')
		OR !Configuration::deleteByName('PAYU_SAFE_KEY')
		OR !Configuration::deleteByName('PAYU_SOAP_USERNAME')
		OR !Configuration::deleteByName('PAYU_SOAP_PASSWORD')
		OR !Configuration::deleteByName('PAYU_3DS_ENABLED')
		OR !Configuration::deleteByName('PAYU_SHOW_BUGET')
		OR !Configuration::deleteByName('PAYU_SERVER_MODE')
		OR !Configuration::deleteByName('PAYU_REDIRECT_URL')
		OR !Configuration::deleteByName('PAYU_PAYMENT_METHODS')
		OR !Configuration::deleteByName('PAYU_CURRENCY')
		OR !Configuration::deleteByName('PAYU_TRANSACTION_TYPE')
		OR !Configuration::deleteByName('PAYU_BASKET_DESC')
		OR !Configuration::deleteByName('PS_OS_AWAITING_PAYMENT')
		OR !$this->deleteOrderState()
		OR !parent::uninstall())
			return false;
		return true;
	}

	public function installCurrency()
	{
		//Check if rands are installed and install and refresh if not
		$currency = new Currency();
		$currency_rand_id  = $currency->getIdByIsoCode('ZAR');

		if(!$currency_rand_id){
			$currency->name = "South African Rand";
			$currency->iso_code = "ZAR";
			$currency->iso_code_num = '710';
			$currency->sign = "R";
			$currency->format = 1;
			$currency->blank = 1;
			$currency->decimals = 1;
			$currency->deleted = 0;
			$currency->active = 1;
			// set it to an arbitrary value, also you can update currency rates to set correct value
			$currency->conversion_rate = 0.45; 
			if($currency->add()) {
				$currency->refreshCurrencies();
			} else {
				return false;
			}
		}
		return true;
	}

	protected function installOrderState()
	{
		$data = array(
			'invoice' => '0',
			'send_email' => '0',
			'module_name' => $this->name,
			'color' => '#FF6600',
			'unremovable' => '1',
			'hidden' => '0',
			'logable' => '0',
			'delivery' => '0',
			'shipped' => '0',
			'paid' => '0',
			'pdf_delivery' => '0',
			'deleted' => '0',
		);
		$db = Db::getInstance(_PS_USE_SQL_SLAVE_);
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		
		if(!(Configuration::get('PS_OS_AWAITING_PAYMENT') > 0)) {
			if($db->insert('order_state', $data)) {
				$id = $db->Insert_ID();
				$data = array(
					'id_order_state' => $id,
					'id_lang' => $lang->id,
					'name' => 'Awaiting payment confirmation',
					'template' => '',
				);
				if($db->insert('order_state_lang', $data)) {
					Configuration::updateValue('PS_OS_AWAITING_PAYMENT', (int)$id);
					return true;
				} 
			}
			return false;
		}
	}
	
	protected function deleteOrderState() 
	{
		
		$id_order_state = (int)Configuration::get('PS_OS_AWAITING_PAYMENT');
		$db = Db::getInstance(_PS_USE_SQL_SLAVE_);
		if($db->delete('order_state', "id_order_state = '$id_order_state'") 
				&& $db->delete('order_state_lang', "id_order_state = '$id_order_state'")) {
			return true;
		}
		return false;
	}
	
	protected function _displayPayU()
	{
		return $this->display(__FILE__, 'infos.tpl');
	}
	
	protected function _postValidation()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			if (!Tools::getValue('PAYU_BASKET_DESC'))
				$this->_postErrors[] = $this->l('Store name is required.');
			elseif (!Tools::getValue('PAYU_MERCHANT_REF'))
				$this->_postErrors[] = $this->l('Merchant reference is required.');
			elseif (!Tools::getValue('PAYU_SAFE_KEY'))
				$this->_postErrors[] = $this->l('Safe key is required.');
			elseif (!Tools::getValue('PAYU_SOAP_USERNAME'))
				$this->_postErrors[] = $this->l('Soap username is required.');
			elseif (!Tools::getValue('PAYU_SOAP_PASSWORD'))
				$this->_postErrors[] = $this->l('Soap password is required.');
			//elseif (!Tools::getValue('PAYU_PAYMENT_METHODS'))
			//	$this->_postErrors[] = $this->l('Payment method is required.');
			elseif (!Tools::getValue('PAYU_CURRENCY'))
				$this->_postErrors[] = $this->l('Currency is required.');
			elseif(!Tools::getValue('PAYU_TRANSACTION_TYPE'))
				$this->_postErrors[] = $this->l('Transaction type is required');
		}
	}
	
	protected function _postProcess()
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			Configuration::updateValue('PAYU_MERCHANT_REF', Tools::getValue('PAYU_MERCHANT_REF'));
			Configuration::updateValue('PAYU_SAFE_KEY', Tools::getValue('PAYU_SAFE_KEY'));
			Configuration::updateValue('PAYU_SOAP_USERNAME', Tools::getValue('PAYU_SOAP_USERNAME'));
			Configuration::updateValue('PAYU_SOAP_PASSWORD', Tools::getValue('PAYU_SOAP_PASSWORD'));
			Configuration::updateValue('PAYU_3DS_ENABLED', Tools::getValue('PAYU_3DS_ENABLED'));
			Configuration::updateValue('PAYU_SHOW_BUDGET', Tools::getValue('PAYU_SHOW_BUDGET'));
			//Configuration::updateValue('PAYU_PAYMENT_METHODS', Tools::getValue('PAYU_PAYMENT_METHODS'));
			Configuration::updateValue('PAYU_CURRENCY', Tools::getValue('PAYU_CURRENCY'));
			Configuration::updateValue('PAYU_SERVER_MODE', Tools::getValue('PAYU_SERVER_MODE'));
			Configuration::updateValue('PAYU_TRANSACTION_TYPE', Tools::getValue('PAYU_TRANSACTION_TYPE'));
			Configuration::updateValue('PAYU_BASKET_DESC', Tools::getValue('PAYU_BASKET_DESC'));
		}
		$this->_html .= $this->displayConfirmation($this->l('Settings updated'));
	}
	
	public function getContent() 
	{
		if (Tools::isSubmit('btnSubmit'))
		{
			$this->_postValidation();
			if (!count($this->_postErrors))
				$this->_postProcess();
			else
				foreach ($this->_postErrors as $err)
					$this->_html .= $this->displayError($err);
		}
		else
			$this->_html .= '<br />';
		
		$this->_html .= $this->_displayPayU();
		$this->_html .= $this->renderForm();
		
		return $this->_html;
	}
	
	public function renderForm()
	{
		$fields_form = array(
			'form' => array(
				'legend' => array(
					'title' => $this->l('Settings'),
					'icon' => 'icon-cogs'
				),
				'input' => array(
					array(
						'type' => 'text',
						'label' => $this->l('Store name'),
						'name' => 'PAYU_BASKET_DESC',
						'required' => true,
						'class' => 'col-sm-8',
					),
					array(
						'type' => 'text',
						'label' => $this->l('Merchant reference'),
						'name' => 'PAYU_MERCHANT_REF',
						'required' => true,
						'class' => 'col-sm-8',
					),
					array(
						'type' => 'text',
						'label' => $this->l('SOAP username'),
						'name' => 'PAYU_SOAP_USERNAME',
						'required' => true,
						'class' => 'col-sm-8',
					),
					array(
						'type' => 'text',
						'label' => $this->l('Safe key'),
						'name' => 'PAYU_SAFE_KEY',
						'required' => true,
						'class' => 'col-sm-8',
					),
					array(
						'type' => 'text',
						'label' => $this->l('SOAP password'),
						'name' => 'PAYU_SOAP_PASSWORD',
						'required' => true,
						'class' => 'col-sm-8',
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Secure3d'),
						'name' => 'PAYU_3DS_ENABLED',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
					),
					array(
						'type' => 'switch',
						'label' => $this->l('Allow budget payment'),
						'desc'	=> $this->l('Applicable to credit card payments'),
						'name' => 'PAYU_SHOW_BUDGET',
						'is_bool' => true,
						'values' => array(
							array(
								'id' => 'active_on',
								'value' => 1,
								'label' => $this->l('Yes')
							),
							array(
								'id' => 'active_off',
								'value' => 0,
								'label' => $this->l('No')
							)
						),
					),
					/*array(
						'type' => 'checkbox',
						'label' => $this->l('Payment methods'),
						'name' => 'PAYU_PAYMENT_METHODS',
						'desc'    => $this->l('Please confirm with PayU before choosing payment methods.'),
						'required' => true,
						'values'  => array(
    						'query' => $this->getPaymentMethods(),                 
    						'id'    => 'id_option',             
    						'name'  => 'name',                
  							'expand' => array(                   
    							['print_total'] => count($options),
    							'default' => 'show',
    							'show' => array('text' => $this->l('show'), 'icon' => 'plus-sign-alt'),
  								'hide' => array('text' => $this->l('hide'), 'icon' => 'minus-sign-alt'),
    						),
						),
					),*/
					array(
						'type' => 'select',
						'label' => $this->l('Transaction type'),
						'name' => 'PAYU_TRANSACTION_TYPE',
						'required' => true,
						'desc' => $this->l('This determines how payment processing will be handled on PayU'),
						'options' => array(
							'default' => array(
								'value' => 0, 
								'label' => $this->l('Choose transaction type')
							),
							'query' => array(
								array(
									'id_option' => 'RESERVE',
									'name' => $this->l('RESERVE'),
								),
								array(
									'id_option' => 'PAYMENT',
									'name' => $this->l('PAYMENT'),
								)
							),
							'id' => 'id_option',
							'name' => 'name',
						),
					),
					array(
						'type' 		=> 'select',
						'label' 	=> $this->l('Currency'),
						'name' 		=> 'PAYU_CURRENCY',
						'required' 	=> true,
						'options' 	=> array(
							'default' => array(
								'value' => 0,
								'label' => $this->l('Choose currency')
							),
							'query' => array(
								array(
									'id_option' => 'ZAR',
									'name' => $this->l('ZAR'),
								),
							),
							'id' => 'id_option',
							'name' => 'name',
						),
					),
					array(
						'type' => 'radio',
						'label' => $this->l('Transaction server'),
						'name' => 'PAYU_SERVER_MODE',
						'desc' => $this->l('Remember to switch to Live Server before accepting real transactions.'),
						'class'		=> 't',
						'is_bool'	=> true,
						'values'	=> array(
							array(
								'id'	=> 'active_on',
								'value'	=> 1,
								'label' => 'Live Server',
							),
							array (
								'id'	=> 'active_off',
								'value'	=> 0,
								'label' => 'Sandbox Server',
							),
						),
					),
				),
				'submit' => array(
					'title' => $this->l('Save'),
				)
			),
		);
		
		$helper = new HelperForm();
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$lang = new Language((int)Configuration::get('PS_LANG_DEFAULT'));
		$helper->default_form_language = $lang->id;
		$helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
		$this->fields_form = array();
		$helper->id = (int)Tools::getValue('id_carrier');
		$helper->identifier = $this->identifier;
		$helper->submit_action = 'btnSubmit';
		$helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->tpl_vars = array(
				'fields_value' => $this->getConfigFieldsValues(),
				'languages' => $this->context->controller->getLanguages(),
				'id_language' => $this->context->language->id
		);
		
		return $helper->generateForm(array($fields_form));
	}	

	public function hookPayment($params)
	{
		if (!$this->active)
			return ;

		$customer = new Customer(intval($params['cart']->id_customer));
		$id_address = Address::getFirstCustomerAddressId(intval($customer->id));
		$address = new Address($id_address);
		$safe_key = Configuration::get('PAYU_SAFE_KEY');
		$merchantReference = Configuration::get('PAYU_MERCHANT_REF');
		$PAYU_PAYMENT_METHODS = Configuration::get('PAYU_PAYMENT_METHODS');
		$PAYU_CURRENCY = Configuration::get('PAYU_CURRENCY');
		$PAYU_BASKET_DESC = Configuration::get('PAYU_BASKET_DESC');
		$PAYU_TRANSACTION_TYPE = Configuration::get('PAYU_TRANSACTION_TYPE');
		$PAYU_3DS_ENABLED = Configuration::get('PAYU_3DS_ENABLED');
		$PAYU_SHOW_BUDGET = Configuration::get('PAYU_SHOW_BUDGET');
		$baseUrl = self::getTransactionServer() . '/rpp.do?PayUReference=';
		$payURppUrl = $baseUrl;
		$apiVersion = 'ONE_ZERO';
			
		$currency = $this->getCurrency();

		if (!Validate::isLoadedObject($customer) OR !Validate::isLoadedObject($currency))
			return $this->l('Invalid customer or currency');
			
		$setTransactionArray = array();
		$setTransactionArray['Api'] = $apiVersion;
		$setTransactionArray['Safekey'] = $safe_key;
		$setTransactionArray['TransactionType'] = $PAYU_TRANSACTION_TYPE;
		$setTransactionArray['AdditionalInformation']['merchantReference'] = $merchantReference;
		
		if($PAYU_3DS_ENABLED) {
			$setTransactionArray['AdditionalInformation']['secure3d'] = true;
		} else {
			$setTransactionArray['AdditionalInformation']['secure3d'] = false;
		}

		if($PAYU_SHOW_BUDGET) {
			$setTransactionArray['AdditionalInformation']['ShowBudget'] = true;
		} else {
			$setTransactionArray['AdditionalInformation']['ShowBudget'] = false;
		}
		$setTransactionArray['AdditionalInformation']['notificationUrl'] = Context::getContext()->link->getModuleLink('payu', 'validation');
		$setTransactionArray['AdditionalInformation']['cancelUrl'] = Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'index.php?controller=order';
		$setTransactionArray['AdditionalInformation']['returnUrl'] = Context::getContext()->link->getModuleLink('payu', 'validation');
		$setTransactionArray['AdditionalInformation']['supportedPaymentMethods'] = $PAYU_PAYMENT_METHODS;

		$setTransactionArray['Basket']['description'] = $PAYU_BASKET_DESC;
		$setTransactionArray['Basket']['amountInCents'] =(int)((number_format(Tools::convertPrice($params['cart']->getOrderTotal(true, Cart::BOTH), $currency), 2, '.', '')) * 100);
		$setTransactionArray['Basket']['currencyCode'] = $PAYU_CURRENCY;

		$setTransactionArray['Customer']['merchantUserId'] = Tools::stripslashes($customer->id);;
		$setTransactionArray['Customer']['email'] = Tools::stripslashes($customer->email);
		$setTransactionArray['Customer']['firstName'] = Tools::stripslashes($customer->firstname);
		$setTransactionArray['Customer']['ip'] = Tools::getRemoteAddr();
		$setTransactionArray['Customer']['lastName'] = Tools::stripslashes($customer->lastname);
		$setTransactionArray['Customer']['mobile'] = 
							isset($address->phone_mobile) ? Tools::stripslashes($address->phone_mobile) :
								(isset($address->phone) ? Tools::stripslashes($address->phone) : '');
		$setTransactionArray['Customer']['regionalId'] = 
							Tools::stripslashes($address->city . '_' . $address->postcode);

		$returnData = $this->setSoapTransaction($setTransactionArray);
		$payUReference = isset($returnData['return']['payUReference']) ? $returnData['return']['payUReference'] : '';
			
		$confirmPayment = false;
		if($payUReference != ''){
			if(Configuration::get('PAYU_SERVER_MODE')){
				$payURppUrl .= $payUReference;
				Configuration::updateValue('PAYU_REDIRECT_URL', $payURppUrl);
			} else {
				$payURppUrl .= $payUReference;
				Configuration::updateValue('PAYU_REDIRECT_URL', $payURppUrl);
			}
			$confirmPayment = true;
		}

		$this->smarty->assign(array(
				'this_path_pu' => $this->_path,
				'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/',
				'confirmUrl' => $payURppUrl,
				'confirmPayment' => $confirmPayment,
		));

		return $this->display(__FILE__, 'payment.tpl');
	}

	public function confirmationUrl()
	{

		$confirmationUrl = Configuration::GET('PAYU_REDIRECT_URL');

		if($confirmationUrl != '')
		{
			Tools::redirect($confirmationUrl);
			exit;
		}
	}

	private static function getTransactionServer()
	{
		if(!Configuration::get('PAYU_SERVER_MODE')) {
			$baseUri = 'https://staging.payu.co.za';
		} else {
			$baseUri = 'https://secure.payu.co.za';
		}
		return $baseUri;
	}

	private static function getSoapHeaderXml()
	{
		$soap_username = Configuration::get('PAYU_SOAP_USERNAME');
		$soap_password = Configuration::get('PAYU_SOAP_PASSWORD');

		$headerXml  = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
		$headerXml .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
		$headerXml .= '<wsse:Username>'.$soap_username.'</wsse:Username>';
		$headerXml .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$soap_password.'</wsse:Password>';
		$headerXml .= '</wsse:UsernameToken>';
		$headerXml .= '</wsse:Security>';

		return $headerXml;
	}

	public function getSoapTransaction($payUReference)
	{
		$apiVersion = 'ONE_ZERO';
		$safeKey = Configuration::get('PAYU_SAFE_KEY');
		$payu_ref = $payUReference;

		$getDataArray = array();
		$getDataArray['Api'] = $apiVersion;
		$getDataArray['Safekey'] = $safeKey;
		$getDataArray['AdditionalInformation']['payUReference'] = $payu_ref;

		$soapCallResult = self::getSoapSingleton()->getTransaction($getDataArray);
		return json_decode(json_encode($soapCallResult), true);
	}

	private function setSoapTransaction($trans_array)
	{
		$setTransactionArray = $trans_array;
		$soapCallResult = self::getSoapSingleton()->setTransaction($setTransactionArray);

		return json_decode(json_encode($soapCallResult), true);
	}

	public static function getSoapSingleton()
	{
		$ns = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
		if(!self::$soapClient)
		{
			$headerXml = self::getSoapHeaderXml();
			$baseUrl = self::getTransactionServer();
			$soapWsdlUrl = $baseUrl.'/service/PayUAPI?wsdl';

			$headerbody = new SoapVar($headerXml, XSD_ANYXML, null, null, null);
			$soapHeader = new SOAPHeader($ns, 'Security', $headerbody, true);

			$soap_client = new SoapClient($soapWsdlUrl, array('trace' => 1, 'exception' => 0));
			$soap_client->__setSoapHeaders($soapHeader);
			
			self::$soapClient = $soap_client;
			
			return $soap_client;
		}
		return self::$soapClient;
	}
	
	public function getConfigFieldsValues()
	{
		return array(
			'PAYU_MERCHANT_REF' => Tools::getValue('PAYU_MERCHANT_REF', Configuration::get('PAYU_MERCHANT_REF')),
			'PAYU_SAFE_KEY' => Tools::getValue('PAYU_SAFE_KEY', Configuration::get('PAYU_SAFE_KEY')),
			'PAYU_SOAP_USERNAME' => Tools::getValue('PAYU_SOAP_USERNAME', Configuration::get('PAYU_SOAP_USERNAME')),
			'PAYU_SOAP_PASSWORD' => Tools::getValue('PAYU_SOAP_PASSWORD', Configuration::get('PAYU_SOAP_PASSWORD')),
			'PAYU_BASKET_DESC' => Tools::getValue('PAYU_BASKET_DESC', Configuration::get('PAYU_BASKET_DESC')),
			'PAYU_3DS_ENABLED' => Tools::getValue('PAYU_3DS_ENABLED', Configuration::get('PAYU_3DS_ENABLED')),
			'PAYU_SHOW_BUDGET'	=> Tools::getValue('PAYU_SHOW_BUDGET', Configuration::get('PAYU_SHOW_BUDGET')),
			'PAYU_PAYMENT_METHODS' => Tools::getValue('PAYU_PAYMENT_METHODS', Configuration::get('PAYU_PAYMENT_METHODS')),
			'PAYU_CURRENCY' => Tools::getValue('PAYU_CURRENCY', Configuration::get('PAYU_CURRENCY')),
			'PAYU_SERVER_MODE' => Tools::getValue('PAYU_SERVER_MODE', Configuration::get('PAYU_SERVER_MODE')),
			'PAYU_TRANSACTION_TYPE' => Tools::getValue('PAYU_TRANSACTION_TYPE', Configuration::get('PAYU_TRANSACTION_TYPE')),
		);
	}
	
	protected function getPaymentMethods()
	{
		$options = array();
		$option = array();
		$payment_methods = (array)explode(', ', Configuration::get('PAYU_PAYMENT_METHODS'));
		
		foreach($payment_methods as $payment_method) {
			$option['id_option'] = $payment_method;
			$option['name'] = $payment_method;
			$options[] = $option;
		}
		
		return $options;
	}
}
?>
