<?php

class Mobbex_Mobbex_Model_Resource_Cache_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Initialize resource collection
     */
    public function _construct()
    {
        $this->_init('mobbex/cache', 'mobbex_cache');
    }
}