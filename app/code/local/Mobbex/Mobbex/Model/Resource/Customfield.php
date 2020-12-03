<?php
 
class Mobbex_Mobbex_Model_Resource_Customfield extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct()
    {
        $this->_init('mobbex/customfield', 'customfield_id');
    }
}