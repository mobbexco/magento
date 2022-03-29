
<?php

class  Mobbex_Mobbex_Model_System_Config_Source_Multivendor
{
    public function toOptionArray($isMultiselect = false)
    {
        return array(
            array('value'=>false, 'label'=>Mage::helper('adminhtml')->__('Disabled')),
            array('value'=>'active', 'label'=>Mage::helper('adminhtml')->__('Active')),
            array('value'=>'unified', 'label'=>Mage::helper('adminhtml')->__('Unified')),
        );
    }
}