<?php

class Mobbex_Mobbex_Block_Adminhtml_Catalog_Category_Tab extends Mage_Adminhtml_Block_Template
{
	/** @var Mobbex_Mobbex_Helper_Settings */
	public $settings;

	/** Common plans fields data. */
	public $commonPlans;

	/** Advanced plans fields data. */
	public $advancedPlans;

	public function _construct()
	{
		$id = Mage::registry('current_category') ? Mage::registry('current_category')->getId() : false;

		if (empty($id))
			return;

		// Get plans fields
		$this->settings		 = Mage::helper('mobbex/settings');
		$this->commonPlans	 = $this->settings->getCommonPlanFields($id, 'category');
		$this->advancedPlans = $this->settings->getAdvancedPlanFields($id, 'category');
		$this->entity        = $this->settings->getEntity($id, 'category');

		$this->setTemplate('mobbex/category-settings.phtml');
	}
}