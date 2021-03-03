<?php

class Mobbex_Mobbex_Model_Observer
{
	/**
	 * Flag to stop observer executing more than once
	 *
	 */
	static protected $_singletonFlag = false;

	public function saveProductTabData()
	{
		if (!self::$_singletonFlag) {
			self::$_singletonFlag = true;

			$product_id = Mage::registry('current_product')->getId();

			try {
				$plans = array(
					'ahora_3' => Mage::app()->getRequest()->getPost('ahora_3'),
					'ahora_6' => Mage::app()->getRequest()->getPost('ahora_6'),
					'ahora_12' => Mage::app()->getRequest()->getPost('ahora_12'),
					'ahora_18' => Mage::app()->getRequest()->getPost('ahora_18'),
				);

				foreach ($plans as $key => $value) {
					if ($value === 'on') {
						Mage::getModel('mobbex/customfield')->saveCustomField($product_id, 'product', $key, 'yes');
					} else {
						Mage::getModel('mobbex/customfield')->saveCustomField($product_id, 'product', $key, 'no');
					}
				}

				return true;

			} catch (Exception $e) {
				Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
			}
		}
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
			if($data){
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