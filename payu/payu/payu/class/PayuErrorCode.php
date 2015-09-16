<?php
/**
 * Prestashop PayU Payment Gateway Extension
 *
 * @category   PayU Payment Gateway
 * @package    Modules_PayU
 * @copyright  Copyright (c) 2015 Netcraft Devops (Pty) Limited
 * @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * @author     Kenneth Onah <kenneth@netcraft-devops.com>
 */
 
if(!defined('_PS_VERSION_'))
	exit;
	
class PayuErrorCode extends Module
{
	protected $_payuErrorCodes = array(
			'P001'	=> 'An error occured with this payment, please contact your merchant',
			'P003'	=> 'An error occured with this payment, please contact your merchant',
			'P004'	=> 'An error occured with this payment, please contact your merchant',
			'P005'	=> 'An error occured with this payment, please contact your merchant',
			'P006'	=> 'An error occured with this payment, please contact your merchant',
	);
	
	public function hasErrorEcode($code)
	{
		return isset($this->_payuErrorCodes[$code]) ? $this->_payuErrorCodes[$code] : '';
	}
}