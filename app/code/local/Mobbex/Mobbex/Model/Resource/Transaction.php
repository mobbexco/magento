<?php
 
class Mobbex_Mobbex_Model_Resource_Transaction extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('mobbex/transaction', 'transaction_mobbex_id');
    }
}