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
        $data           = $this->mobbexTransaction->getMobbexTransaction(['order_id' => $this->getInfo()->getOrder()->getIncrementId(), 'parent' => 1]);
        $data['childs'] = !empty($mobbexData['childs']) ? $this->mobbexTransaction->getMobbexChilds(json_decode($mobbexData['childs'], true), $mobbexData['order_id']) : false;
        $data['coupon'] = "https://mobbex.com/console/" . $data['entity_uid'] . "/operations/?oid=" . $data['payment_id'];

        return $data;
    }
}
