<?php

class Mobbex_Mobbex_Model_Cache extends Mage_Core_Model_Abstract
{
    /**
     * Initialize Model
     */
    protected function _construct()
    {
        $this->_init('mobbex/cache');
    }

    /**
     * Get data stored in mobbex chache table.
     * 
     * @param string $key Identifier key for cache data.
     * @return string|bool $data Data to store.
     */
    public function get($key)
    {
        //Get connection
        $resource   = \Mage::getSingleton('core/resource');

        //Delete expired cache
        $resource->getConnection('core_write')->query("DELETE FROM " . $resource->getTableName('mobbex_cache') . " WHERE `date` < DATE_SUB(NOW(), INTERVAL 5 MINUTE);");

        $collection = $this->getCollection()
            ->addFieldToFilter('cache_key', $key)
            ->getColumnValues('data');

        return !empty($collection[0]) ? json_decode($collection[0], true) : false;
    }

    /**
     * Store data in mobbex cache table.
     * 
     * @param string $key Identifier key for data to store.
     * @param string $data Data to store.
     * @return boolean
     */
    public function store($key, $data)
    {
        //Asign data
        $this->setData('cache_key', $key);
        $this->setData('data', $data);
        $this->setData('date', date('Y-m-d H:i:s'));
        //Save data
        return $this->save();
    }
}
