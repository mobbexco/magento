<?php

class Mobbex_Mobbex_Block_Finance_Widget extends Mage_Core_Block_Template
{

    public $styles = [];

    public function _construct()
    {
        parent::_construct();
        $this->setTemplate('mobbex/finance.phtml');

        // Init class properties
        \Mage::helper('mobbex/instantiator')->setProperties($this, ['sdk', 'settings', '_checkoutSession']);
        
        //init widget
        $this->action = strpos(Mage::helper('core/url')->getCurrentUrl(), 'cart') ? 'cart' : 'product';
        $this->initWidget();
        $this->configs = $this->settings->getAll();

    }

    public function initWidget()
    {
        if ((!$this->settings->get('financing_product') && $this->action === 'product') || (!$this->settings->get('financing_cart') && $this->action === 'cart'))
            return $this->enable = false;

        // Get current objects
        $product = Mage::registry('current_product');
        $quote   = $this->_checkoutSession->getQuote();
        
        if ($this->action == 'product' ? !$product->isSaleable() : !$quote->hasItems())
            return $this->enable = false;
        
        $total    = $this->action === 'product' ?   $product->getPrice() : $quote->getGrandTotal();
        $products = $this->action === 'product' ? [$product] : [];
        
        if ($this->action === 'cart') {
            foreach ($quote->getAllVisibleItems() as $item)
            $products[] = $item->getProduct();
        }
        
        extract($this->settings->getProductPlans($products));
        $this->sources = \Mobbex\Repository::getSources($total, \Mobbex\Repository::getInstallments($this->products, $common_plans, $advanced_plans));
        
        return $this->enable = true;
    }
    
}
