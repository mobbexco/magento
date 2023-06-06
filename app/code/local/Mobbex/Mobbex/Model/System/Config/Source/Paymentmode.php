
<?php

class  Mobbex_Mobbex_Model_System_Config_Source_Paymentmode
{
    public function toOptionArray($isMultiselect = false)
    {
        return array(
            array('value' => 'payment.v2', 'label' => Mage::helper('mobbex')->__('Default')),
            array('value' => 'payment.2-step', 'label' => Mage::helper('mobbex')->__('Two Step Payment')),
        );
    }
}
