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
}