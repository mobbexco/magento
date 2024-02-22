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
        $data['childs'] = !empty($data['childs']) ? $this->mobbexTransaction->getMobbexChilds(json_decode($data['childs'], true), $data['order_id']) : [];

        return $data;
    }
}
