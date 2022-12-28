<?php

class Mobbex_Mobbex_Block_Finance_Widget extends Mage_Core_Block_Template
{


    public function _construct()
    {
        parent::_construct();

        $this->settings        = Mage::helper('mobbex/settings');
        $this->mobbex          = $this->settings->helper;
        $this->checkoutSession = Mage::getSingleton('checkout/session');
        $this->action          = strpos(Mage::helper('core/url')->getCurrentUrl(), 'cart') ? 'cart' : 'product';

        //init widget
        $this->initWidget();
    }

    public function initWidget()
    {
        if ((!Mage::getStoreConfig('payment/mobbex/financing_product') && $this->action === 'product') || (!Mage::getStoreConfig('payment/mobbex/financing_cart') && $this->action === 'cart'))
            return $this->enable = false;

        // Get current objects
        $product = Mage::registry('current_product');
        $quote   = $this->checkoutSession->getQuote();

        if ($this->action == 'product' ? !$product->isSaleable() : !$quote->hasItems())
            return $this->enable = false;

        $total   = $this->action === 'product' ?   $product->getPrice() : $quote->getGrandTotal();
        $products = $this->action === 'product' ? [$product] : [];

        if ($this->action === 'cart') {
            foreach ($quote->getAllVisibleItems() as $item)
                $products[] = $item->getProduct();
        }

        $this->sources = $this->mobbex->getSources($total, $this->mobbex->getInstallments($products));
        $this->styles  = [
            'theme' => Mage::getStoreConfig('payment/mobbex/theme'),
            'text'  => Mage::getStoreConfig('payment/mobbex/button_text'),
            'logo'  => Mage::getStoreConfig('payment/mobbex/button_logo'),
            'css'   => Mage::getStoreConfig('payment/mobbex/widget_style')
        ];

        return $this->enable = true;
    }
}
