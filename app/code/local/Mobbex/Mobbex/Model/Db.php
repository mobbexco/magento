<?php

class Mobbex_Mobbex_Model_Db extends Mobbex\Model\Db
{
    /** @var Varien_Db_Adapter_Interface */
    public $read;

    /** @var Varien_Db_Adapter_Interface */
    public $write;

    /**
     * Constructor.
     * 
     * @param mixed $prefix Db tables prefix.
     */
    public function __construct($prefix = null)
    {
        $resource = \Mage::getSingleton('core/resource');

        $this->read  = $resource->getConnection('core_read');
        $this->write = $resource->getConnection('core_write');

        parent::__construct($prefix ?: (string) \Mage::getConfig()->getTablePrefix());
    }

    /**
     * Executes the sql script given on db.
     * 
     * @param string $sql
     * 
     * @return array|bool
     */
    public function query($sql)
    {
        return $this->isRead($sql) ? $this->read->query($sql)->fetchAll() : $this->write->query($sql);
    }

    /**
     * Check if the sql query given is read type.
     * 
     * @param string $sql
     * 
     * @return bool
     */
    public function isRead($sql)
    {
        return (bool) preg_match(
            '#^\s*\(?\s*(select|show|explain|describe|desc)\s#i',
            $sql
        );
    }
}