<?php

class Mobbex_Mobbex_Helper_Logger extends Mage_Core_Helper_Abstract
{
    public function __construct()
    {
        // Init class properties
        \Mage::helper('mobbex/instantiator')->setProperties($this, ['settings']);
    }

    /**
     * Log Mobbex errors and other useful data with magento log system.
     * 
     * Mode debug: Log data only if debug mode is active
     * Mode error: Always log data.
     * Mode fatal: Always log data & stop code execution.
     * 
     * @param string $mode 
     * @param string $message
     * @param string $data
     */
    public function log($mode, $message, $data = [])
    {
        //Add error message
        if ($mode === 'error')
            Mage::getSingleton('core/session')->addError(Mage::helper('mobbex')->__($message));
        //Get data message
        $message = $message . json_encode($data);
        //Debug mode only if debug is active
        $force = $mode === 'debug' ? $this->settings->get('debug_mode') : true;
        //Log data
        Mage::log($message, null, "mobbex_$mode" . "_" . date('m_Y') . ".log", $force);
        //If fatal mode stop code execution.
        if($mode === 'fatal')
            die;
    }    
}