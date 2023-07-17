<?php

class Mobbex_Mobbex_Block_Payment_Methods extends Mage_Core_Block_Template
{
    public function _construct()
    {
        $this->setTemplate('mobbex/methods.phtml');
        parent::_construct();

        // Init class properties
        Mage::helper('mobbex/instantiator')->setProperties($this, ['sdk', 'helper', 'logger', '_checkoutSession', '_quote']);

        $this->data = $this->getMobbexMethods();
    }

    public function getMobbexMethods()
    {
        if ($this->data)
            return $this->data;

        $data = ['methods' => [], 'cards' => []];
        $checkoutData = $this->helper->createCheckoutFromQuote($this->getQuoteData());

        if (isset($checkoutData['paymentMethods'])) {

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

        if (isset($checkoutData['wallet'])) {

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
        $quote          = $this->_quote->load($this->_checkoutSession->getQuoteId());
        $billingData    = $quote->getBillingAddress()->getData();

        $quoteData = [
            'entity_id'        => $quote->getId(),
            'customer_id'      => $quote->getCustomer()->getId(),
            'price'            => $quote->getGrandTotal(),
            'currency_id'      => $quote->getStore()->getCurrentCurrency()->getCode(),
            'email'            => $quote->getCustomerEmail(),
            'customer'         => [
                'firstname'            => $billingData['firstname'],
                'lastname'             => $billingData['lastname'],
                'telephone'            => $billingData['telephone'],
                'save_in_address_book' => 1
            ],
            'items'          => [],
            'shipping_total' => $quote->getShippingAddress()->getShippingAmount(),
            'addresses'      => $this->helper->getAddresses([$billingData, $quote->getShippingAddress()->getData()]),
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
