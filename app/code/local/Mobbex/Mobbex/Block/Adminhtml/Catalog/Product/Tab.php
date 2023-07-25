<?php

class Mobbex_Mobbex_Block_Adminhtml_Catalog_Product_Tab extends Mage_Adminhtml_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

	public function _construct()
	{
		parent::_construct();

		$this->rowId = Mage::registry('current_product') ? Mage::registry('current_product')->getId() : false;

		if (!$this->rowId)
			return;

		\Mage::helper('mobbex/instantiator')->setProperties($this, ['sdk', 'settings', 'helper']);

		$this->setTemplate('mobbex/product-settings.phtml');
	}

	public function getTabLabel()
	{
		return $this->__('Mobbex Options');
	}

	public function getTabTitle()
	{
		return $this->__('Edit payment plans for this product');
	}

	public function canShowTab()
	{
		return true;
	}

	public function isHidden()
	{
		return false;
	}
}