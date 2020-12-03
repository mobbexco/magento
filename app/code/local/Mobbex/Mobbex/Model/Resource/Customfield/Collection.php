<?php
 
class Mobbex_Mobbex_Model_Resource_Customfield_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    public function _construct()
    {
        $this->_init('mobbex/customfield', 'mobbex_customfield');
    }
}