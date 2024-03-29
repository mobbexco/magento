<?php
require_once __DIR__ . '/../vendor/autoload.php';

class Mobbex_Mobbex_Helper_Instantiator extends Mage_Core_Helper_Abstract
{
    public $classes = [
        'sdk'               => 'mobbex/sdk',
        'settings'          => 'mobbex/settings',
        'helper'            => 'mobbex',
        'logger'            => 'mobbex/logger',
        'customField'       => 'mobbex/customfield',
        'cache'             => 'mobbex/cache',
        'mobbexTransaction' => 'mobbex/transaction',
        '_checkoutSession'  => 'checkout/session',
        '_quote'            => 'sales/quote',
        '_order'            => 'sales/order',
        'db'                => 'mobbex/db',
    ];

    public $type = [
        'helper'       => ['sdk', 'settings', 'helper', 'logger'],
        'getModel'     => ['customField', 'cache', 'mobbexTransaction', '_quote', '_order', 'db'],
        'getSingleton' => ['_checkoutSession'],
    ];

    /**
     * Create an instance of slected classes & set them as propertys of the current class.
     * @param Object $object
     * @param array $properties
     */
    public function setProperties($object, $properties)
    {
        foreach ($properties as $property) {
            $method = $this->getMethod($property);
            $object->$property = Mage::$method($this->classes[$property]);
            if ($property === 'sdk')
                $object->sdk->init();
        }
    }

    public function getMethod($property)
    {
        foreach ($this->type as $method => $value) {
            if(in_array($property, $value))
                return $method;
        }
    }
}
