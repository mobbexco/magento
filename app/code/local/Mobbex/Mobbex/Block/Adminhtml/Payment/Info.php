<?php

class Mobbex_Mobbex_Block_Adminhtml_Payment_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mobbex/info.phtml');

        $this->mobbexTransaction = Mage::getModel('mobbex/transaction');
    }
}
