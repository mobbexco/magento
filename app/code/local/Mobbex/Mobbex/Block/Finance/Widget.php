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
        $this->styles          = $this->getStyles();

    }

    /**
     * Return the styles for the widget.
     * @return array
     */
    public function getStyles()
    {
        //Styles
        $this->styles = [
            'theme' => Mage::getStoreConfig('payment/mobbex/theme'),
            'text'  => Mage::getStoreConfig('payment/mobbex/button_text'),
            'logo'  => Mage::getStoreConfig('payment/mobbex/button_logo'),
            'css'   => Mage::getStoreConfig('payment/mobbex/widget_style')
        ];

        return $this->styles;
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
        $this->action = $this->getRequest()->getActionName();

        //Get product data
        $product = Mage::registry('current_product') ?: false;
        $quote   = $this->checkoutSession->getQuote();

        // Exit if options are disabled or product is not salable
        if ($this->action == 'catalog_product_view' ? !Mage::getStoreConfig('payment/mobbex/financing_product') || !$product->isSaleable() : !Mage::getStoreConfig('payment/mobbex/financing_cart'))
            return $this->unsetChild($this->getNameInLayout());

        $this->total    = $this->action == 'catalog_product_view' ? $product->getPrice() : $quote->getGrandTotal();
        $this->products = $this->action == 'catalog_product_view' ? [$product->getId()] : $quote->getAllVisibleItems();
        $this->sources  = $this->mobbex->getSources($this->total, $this->mobbex->getInstallments($this->products));
        
        return $this->sources;
    }

}
