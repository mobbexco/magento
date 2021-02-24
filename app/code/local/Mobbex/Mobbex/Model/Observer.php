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


	public function saveCategoryTabData()
	{
		if (!self::$_singletonFlag) {
			self::$_singletonFlag = true;

			$category_id = Mage::registry('current_category')->getId();

			try {
				$plans = array(
					'ahora_3' => Mage::app()->getRequest()->getPost('ahora_3'),
					'ahora_6' => Mage::app()->getRequest()->getPost('ahora_6'),
					'ahora_12' => Mage::app()->getRequest()->getPost('ahora_12'),
					'ahora_18' => Mage::app()->getRequest()->getPost('ahora_18'),
				);

				foreach ($plans as $key => $value) {
					if ($value === 'on') {
						Mage::getModel('mobbex/customfield')->saveCustomField($category_id, 'category', $key, 'yes');
					} else {
						Mage::getModel('mobbex/customfield')->saveCustomField($category_id, 'category', $key, 'no');
					}
				}

				return true;

			} catch (Exception $e) {
				Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
			}
		}
	}


	/**
	 * Add a new tab in the backoffice category page 
	 */
	public function newTabCategory($observer)
	{
	if (!self::$_singletonFlag) {
		self::$_singletonFlag = true;
		$tabs = $observer->getTabs();
		$category_id = Mage::registry('current_category')->getId();
		// Get plans with current values
		$plans = $this->getPlans($category_id);
		$plans_html = '';
		$full_html = '';

		foreach ($plans as $plan => $data) {
			$enabled = ($data['value'] === 'yes');
			$plans_html .=
						"<tr>
							<td class='value'>
								<input type='checkbox' name='".$plan."' id='".$plan."' " . ($enabled ? 'checked' : '') . ">
							</td>
							<td class='label' style='width: 100%;'><label for='" . $plan . "'>" . $data['label'] . "</label></td>
						</tr>";
		}
			
		$full_html ='
			<div class="entry-edit">
				<div class="entry-edit-head">
					<h4 class="icon-head head-edit-form fieldset-legend">Mobbex Options</h4>
				</div>
				<div class="fieldset fieldset-wide" id="mobbex_options">
					<div class="hor-scroll">
						<table cellspacing="0" class="form-list">
							<tbody>
								<h3>Elija los planes que NO quiera que aparezcan durante la compra</h3>
								'.$plans_html.'
							</tbody>
						</table>
					</div>
				</div>
			</div>';
		$tabs->addTab('customtab', array(
			'label'     => 'Mobbex Plans',
			'content'   => $full_html
		));
	}
	}


	private  function getPlans($category_id){

		$ahora = array(
			'ahora_3' => array(
				'label' => 'Ahora 3',
				'value' => Mage::getModel('mobbex/customfield')->getCustomField($category_id, 'category', 'ahora_3'),
			),
			'ahora_6' => array(
				'label' => 'Ahora 6',
				'value' => Mage::getModel('mobbex/customfield')->getCustomField($category_id, 'category', 'ahora_6'),
			),
			'ahora_12' => array(
				'label' => 'Ahora 12',
				'value' => Mage::getModel('mobbex/customfield')->getCustomField($category_id, 'category', 'ahora_12'),
			),
			'ahora_18' => array(
				'label' => 'Ahora 18',
				'value' => Mage::getModel('mobbex/customfield')->getCustomField($category_id, 'category', 'ahora_18'),
			),
		);
		return $ahora;
	}
}