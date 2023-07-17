<?php

class Mobbex_Mobbex_Helper_Logger extends Mage_Core_Helper_Abstract
{
    public function __construct()
    {
        // Init class properties
        \Mage::helper('mobbex/instantiator')->setProperties($this, ['settings']);
    }

    /**
     * Log Mobbex errors and other useful data to magento log system if debug mode is active.
     * @param string $mode 
     * @param string $message
     * @param string $data
     */
    public function debug($mode, $message, $data = [])
    {
        if ($mode === 'error')
            Mage::getSingleton('core/session')->addError(Mage::helper('mobbex')->__($message));

        $message = $message . json_encode($data);
        $force = $mode === 'debug' ? $this->settings->get('debug_mode') : true;

        return Mage::log($message, null, "mobbex_$mode" . "_" . date('m_Y') . ".log", $force);
    }    
}