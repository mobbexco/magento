<?php

class Mobbex_Mobbex_Block_Adminhtml_Catalog_Category_Tab extends Mage_Adminhtml_Block_Template
{
	public function _construct()
	{
		$this->rowId = Mage::registry('current_category') ? Mage::registry('current_category')->getId() : false;

		if (!$this->rowId)
			return;

		\Mage::helper('mobbex/instantiator')->setProperties($this, ['sdk', 'settings', 'helper']);
		
		$this->setTemplate('mobbex/category-settings.phtml');
	}
}