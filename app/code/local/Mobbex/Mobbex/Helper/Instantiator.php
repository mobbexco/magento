<?php
require_once __DIR__ . '/../vendor/autoload.php';

class Mobbex_Mobbex_Helper_Instantiator extends Mage_Core_Helper_Abstract
{
    public $classes = [
        'sdk'               => 'mobbex/sdk',
        'settings'          => 'mobbex/settings',
        'helper'            => 'mobbex/mobbex',
        'logger'            => 'mobbex/logger',
        'customField'       => 'mobbex/customfield',
        'mobbexTransaction' => 'mobbex/transaction',
        '_checkoutSession'  => 'checkout/session',
        '_quote'            => 'sales/quote',
        '_order'            => 'sales/order',
    ];

    public $type = [
        'helper'       => ['sdk', 'settings', 'helper', 'logger'],
        'getModel'     => ['customField', 'mobbexTransaction', '_quote', '_order'],
        'getSingleton' => ['_checkoutSession'],
    ];

    /**
     * Create an instance of slected classes & set them as properties of the current class.
     * @param Object $object
     * @param array $properties
     */
    public function setProperties($object, $properties)
    {
        foreach ($properties as $propertie) {
            foreach ($this->type as $key => $value) {
                if (in_array($propertie, $value))
                    $method = $key;
            }
            $object->$propertie = Mage::$method($this->classes[$propertie]);
            if ($propertie === 'sdk')
                $object->sdk->init();
        }
    }
}
