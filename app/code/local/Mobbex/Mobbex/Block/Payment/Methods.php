<?php

class Mobbex_Mobbex_Block_Payment_Methods extends Mage_Core_Block_Template
{
    public function _construct()
    {
        $this->setTemplate('mobbex/methods.phtml');
        parent::_construct();

        $this->mobbex = Mage::helper('mobbex/data');
        $this->data = $this->getMobbexMethods();
        
    }

    public function getMobbexMethods()
    {
        if($this->data)
            return $this->data;
        
        $data = ['methods' => [], 'cards' => []];
        $checkoutData = $this->mobbex->createCheckoutFromQuote($this->getQuoteData());

        if(isset($checkoutData['paymentMethods'])){             
        
            foreach ($checkoutData['paymentMethods'] as $method) {
                $data['methods'][] = [
                    'id'    => $method['subgroup'],
                    'value' => $method['group'] . ':' . $method['subgroup'],
                    'name'  => $method['subgroup_title'],
                    'image' => $method['subgroup_logo']
                ];
            }

        } else {
            $data['methods'][] = [
                'id'    => 'mobbex',
                'value' => '',
                'name'  => 'Pagar con Mobbex',
                'image' => ''
            ]; 
        }

        if(isset($checkoutData['wallet'])) {
           
            foreach ($checkoutData['wallet'] as $key => $card) {
                $data['cards'][] = [
                    'id'           => 'wallet-card-' . $key,
                    'value'        => 'card-' . $key,
                    'name'         => $card['name'],
                    'image'          => $card['source']['card']['product']['logo'],
                    'maxlength'    => $card['source']['card']['product']['code']['length'],
                    'placeholder'  => $card['source']['card']['product']['code']['name'],
                    'hiddenValue'  => $card['card']['card_number'],
                    'installments' => $card['installments']
                ];
            }
        }

        return $data;
    }

    public function getQuoteData()
    {
        $session        = Mage::getSingleton('checkout/session');
        $quote          = Mage::getModel('sales/quote')->load($session->getQuoteId());
        $shipAdressData = $quote->getBillingAddress()->getData();
        
        $quoteData = [
            'entity_id'        => $quote->getId(),
            'customer_id'      => $quote->getCustomer()->getId(),
            'price'            => $quote->getGrandTotal(),
            'currency_id'      => $quote->getStore()->getCurrentCurrency()->getCode(),
            'email'            => $quote->getCustomerEmail(),
            'shipping_address' => [
                'firstname'            => $shipAdressData['firstname'],
                'lastname'             => $shipAdressData['lastname'],
                'street'               => $shipAdressData['street'],
                'city'                 => $shipAdressData['city'],
                'region'               => $shipAdressData['region'],
                'postcode'             => $shipAdressData['postcode'],
                'telephone'            => $shipAdressData['telephone'],
                'save_in_address_book' => 1
            ],
            'items'          => [],
            'shipping_total' => $quote->getShippingAddress()->getShippingAmount(),
            'quote'          => $quote
        ];

        foreach ($quote->getAllVisibleItems() as $item)
            $quoteData['items'][] = $item->getProduct();


        return $quoteData;
    }

    public function getMethodLabelAfterHtml()
    {
        return null;
    }

    public function hasMethodTitle()
    {
        return false;
    }

    public function getMethodTitle()
    {
        return 'Pague con tarjetas y otros medios de pago';
    }
}