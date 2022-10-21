<?php

class Mobbex_Mobbex_Helper_Sdk extends Mage_Core_Helper_Abstract
{
    /** @var Mobbex_Webpay_Helper_Instantiator */
    public $instantiator;

    public function __construct() {
        \Mage::helper('mobbex/instantiator')->setProperties($this, ['settings', 'helper']);
    }

    /**
     * Allow to use SDK classes.
     */
    public function init()
    {
        // Set platform information
        \Mobbex\Platform::init('magento_1', '2.0', Mage::getBaseUrl(),
        [
            'magento' => Mage::getVersion(),
            'webpay'  => 2.0,
            'sdk'     => \Composer\InstalledVersions::getVersion('mobbexco/php-plugins-sdk'),
        ], $this->settings->getAll(), [$this->helper, 'executeHook']);

        // Init api conector
        \Mobbex\Api::init();
    }
}