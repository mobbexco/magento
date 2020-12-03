<?php

class Mobbex_Mobbex_Block_Adminhtml_Catalog_Product_Tab extends Mage_Adminhtml_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

	public function _construct()
	{
		parent::_construct();
		$this->setTemplate('mobbex/product_tab.phtml');
	}

	public function getTabLabel()
	{
		return $this->__('Mobbex Options');
	}

	public function getTabTitle()
	{
		return $this->__('Click here to edit payment plans on this product');
	}

	public function canShowTab()
	{
		return true;
	}

	public function isHidden()
	{
		return false;
	}

	public function getPlans()
	{
		$product_id = Mage::registry('current_product')->getId();

		$ahora = array(
			'ahora_3' => array(
				'label' => 'Ahora 3',
				'value' => Mage::getModel('mobbex/customfield')->getCustomField($product_id, 'product', 'ahora_3'),
			),
			'ahora_6' => array(
				'label' => 'Ahora 6',
				'value' => Mage::getModel('mobbex/customfield')->getCustomField($product_id, 'product', 'ahora_6'),
			),
			'ahora_12' => array(
				'label' => 'Ahora 12',
				'value' => Mage::getModel('mobbex/customfield')->getCustomField($product_id, 'product', 'ahora_12'),
			),
			'ahora_18' => array(
				'label' => 'Ahora 18',
				'value' => Mage::getModel('mobbex/customfield')->getCustomField($product_id, 'product', 'ahora_18'),
			),
		);

		return $ahora;
	}
}