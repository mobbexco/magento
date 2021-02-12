<?php
 
class Mobbex_Mobbex_Model_Resource_Transaction_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    public function _construct()
    {
        $this->_init('mobbex/transaction', 'mobbex_transaction');
    }
}