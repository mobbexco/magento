<?php


class Mobbex_Mobbex_Block_Finance_Widget extends Mage_Core_Block_Template
{
	public function _construct()
	{
        $this->setTemplate('mobbex/product.phtml');

		parent::_construct();

		$this->settings = Mage::helper('mobbex/settings');
		$this->mobbex   = Mage::helper('mobbex/data');
        $this->sources  = $this->getSources();

    }

    /**
     * Return the Sources with the filtered plans
     * @return array
     */
    public function getSources() {

        if(isset($this->sources)) {
            return $this->sources;
        }

        //Get product data
        $product_id    = Mage::registry('current_product') ? Mage::registry('current_product')->getId() : false;
        $product_price = Mage::registry('current_product') ? Mage::registry('current_product')->getPrice() : false;
        
        //Get product plans
        $inactive_plans = $this->mobbex->getInactivePlans($product_id);
        $active_plans   = $this->mobbex->getActivePlans($product_id);

        //Get the sources filtered
        $sources = $this->mobbex->getSources($product_price, $inactive_plans, $active_plans);
        
        return $this->sources = $sources;
    }

}
