<?php
class Mobbex_Mobbex_Model_Standard extends Mage_Payment_Model_Method_Abstract {
	protected $_code = 'mobbex';
	
	protected $_isInitializeNeeded      = true;
	protected $_canUseInternal          = true;
	protected $_canUseForMultishipping  = false;

	protected $_isGateway = true;
    protected $_canAuthorize = false;
    protected $_canCapture = true;
	protected $_canRefund = true;

}
?>