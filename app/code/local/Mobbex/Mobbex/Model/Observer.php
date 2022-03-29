<?php

class Mobbex_Mobbex_Model_Observer
{
	/** @var Mobbex_Mobbex_Helper_Settings */
	public $settings;

    /** Flag to stop observer executing more than once */
    static protected $_singletonFlag = false;

	public function __construct() {
		$this->settings	= Mage::helper('mobbex/settings');
	}

	/**
	 * Add a new tab in the backoffice category page.
	 * 
	 * @param Varien_Event_Observer $observer
	 */
	public function newTabCategory($observer)
	{
		if (self::$_singletonFlag)
			return;

		self::$_singletonFlag = true;

		$tabs = $observer->getTabs();

		// Create Mobbex Options tab
		$tabs->addTab('mobbex_configuration', [
			'label'   => 'Mobbex Options',
			'content' => $tabs->getLayout()->createBlock('mobbex/adminhtml_catalog_category_tab')->toHtml()
		]);
    }

	public function saveProductTabData()
	{
		if (self::$_singletonFlag)
			return;

		self::$_singletonFlag = true;

		$id = Mage::registry('current_product') ? Mage::registry('current_product')->getId() : false;

		// Exit if it's associated products save
		if (empty($id))
			return;

		$this->settings->savePlanFields($id);
		$this->settings->saveProductSubscription($id);
	}

	public function saveCategoryTabData()
	{
		if (self::$_singletonFlag)
		return;

		self::$_singletonFlag = true;

		$id = Mage::registry('current_category') ? Mage::registry('current_category')->getId() : false;

		if (empty($id))
			return;

		$this->settings->savePlanFields($id, 'category');
	}

	/**
	 * Save dni from checkout billing information
	 */
	public function saveMobbexDni($observer)
	{
		$data = $observer->getEvent()->getControllerAction()->getRequest()->getPost('billing', array());
		$customerData = Mage::getSingleton('customer/session')->getCustomer();

		if ($customerData && !empty($data['dni']))
			Mage::getModel('mobbex/customfield')->saveCustomField($customerData->getId(), 'customer', 'dni', $data['dni']);

		return true;
	}

	/**
	 * Calculates the refund amount of an order
	 * @param $observer : Varien_Event_Observer
	 * @return boolean
	 */
	public function informRefundData(Varien_Event_Observer $observer)
    {
        if (!self::$_singletonFlag) {
			$result = false;
			self::$_singletonFlag = true;
			$creditmemo = $observer->getEvent()->getCreditmemo();
			$order = $observer->getEvent()->getCreditmemo()->getOrder();
			$orderId = $order->getData('increment_id');
			$data = Mage::getModel('mobbex/transaction')->getMobbexTransaction($orderId);//get transaction data
			if(isset($data['data'])){
				$payment = $order->getPayment();
				$transactionId = $payment->getData('last_trans_id');
				$amount = $creditmemo->getData('grand_total');	
				$result = $this->sendRefund($transactionId,$amount);
			}

			return $result;
        }
    }

	/**
	 * Handle an order refund total and partial
	 * @param	$transactionId : integer
	 * @param	$amount : real
	 * @return	 boolean
	 */
	private function sendRefund($transactionId,$amount)
	{
		// Init Curl
		$curl = curl_init();
		$headers = Mage::helper('mobbex/data')->getHeaders();

		curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.mobbex.com/p/operations/".$transactionId."/refund",
            CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode(['total' => floatval($amount)]),
			CURLOPT_HTTPHEADER => $headers
		]);

		$response = curl_exec($curl);
		$err = curl_error($curl);
		
		curl_close($curl);

		if ($err) {
            return false;
        } else {
			$result = json_decode($response['body']);	
			if ($result->result) {
				return true;
			} else {
				return false;
			}
        }
	}
}