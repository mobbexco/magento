<?php

class Mobbex_Mobbex_Block_Adminhtml_Payment_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mobbex/info.phtml');

        // Init class properties
        \Mage::helper('mobbex/instantiator')->setProperties($this, ['mobbexTransaction']);

    }

    /**
     * Return the payment data from mobbex transaction model.
     * @return array $data
     */
    public function getPaymentData()
    {
        $data           = $this->mobbexTransaction->getMobbexTransaction($this->getInfo()->getOrder()->getIncrementId(), [true, true]);
        $data['cards']  = $this->filterCards($data && $data['operation_type'] == 'payment.multiple-sources' ? $this->mobbexTransaction->getMobbexTransaction($this->getInfo()->getOrder()->getIncrementId(), [true, false]) : false);
        $data['coupon'] = isset($data['entity_uid']) && isset($data['payment_id']) ? "https://mobbex.com/console/" . $data['entity_uid'] . "/operations/?oid=" . $data['payment_id'] : '';

        return $data;
    }

    /**
     * Eliminates duplicated cards.
     * @param array
     * @return array
     */
    public function filterCards($cards)
    {
        if(!$cards)
            return false;

        $filter = $data = [];

        foreach ($cards as $key => $card)
            $filter['_' . $key] = $card['payment_id'];

        $filter = array_unique($filter);

        foreach ($cards as $key => $card) {
            $pos = '_' . $key;
            if (isset($filter[$pos])) {
                $data[] = $card;
            }
        }

        return $data;
    }
}
