<?php

class Mobbex_Mobbex_Block_Adminhtml_Catalog_Category_Tab extends Mage_Adminhtml_Block_Template
{
	public function _construct()
	{
		$id = Mage::registry('current_category') ? Mage::registry('current_category')->getId() : false;

		if (empty($id))
			return;

		\Mage::helper('mobbex/instantiator')->setProperties($this, ['sdk', 'settings', 'helper']);
		$this->idCategory = $id;
		
		$this->setTemplate('mobbex/category-settings.phtml');
	}
}