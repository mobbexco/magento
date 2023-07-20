<?php

class Mobbex_Mobbex_Model_Resource_Cache extends Mage_Core_Model_Resource_Db_Abstract
{
    protected $_isPkAutoIncrement = false;
    
    /**
     * Initialize resource
     */
    public function _construct()
    {
        $this->_init('mobbex/cache', 'cache_key');
    }
}