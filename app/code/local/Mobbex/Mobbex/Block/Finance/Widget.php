<?php

class Mobbex_Mobbex_Block_Finance_Widget extends Mage_Core_Block_Template
{


	public function _construct()
	{
        $this->setTemplate('mobbex/product.phtml');
		parent::_construct();
        $this->settings        = Mage::helper('mobbex/settings');
		$this->mobbex          = Mage::helper('mobbex/data');
        $this->checkoutSession = Mage::getSingleton('checkout/session');
        $this->sources         = $this->getSources();
    }

    /**
     * Return the Sources with the filtered plans
     * @return array
     */
    public function getSources() {

        if(isset($this->sources)) {
            return $this->sources;
        }

        //get action name
        $action = $this->getRequest()->getActionName();

        //Get product data
        $product = Mage::registry('current_product') ?: false;
        $quote   = $this->checkoutSession->getQuote();

        // Exit if options are disabled or product is not salable
        if ($action == 'catalog_product_view' ? !Mage::getStoreConfig('payment/mobbex/financing_product') || !$product->isSaleable() : !Mage::getStoreConfig('payment/mobbex/financing_cart'))
            return $this->unsetChild($this->getNameInLayout());

        $this->total    = $action == 'catalog_product_view' ? $product->getPrice() : $quote->getGrandTotal();
        $this->products = $action == 'catalog_product_view' ? [$product->getId()] : $quote->getAllVisibleItems();
        $this->sources  = $this->mobbex->getSources($this->total, $this->mobbex->getInstallments($this->products));
        
        return $this->sources;
    }

}
