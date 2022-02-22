<?php

class Mobbex_Mobbex_Model_Config_Source_Theme
{
    public function toOptionArray()
    {
        return array(
            array(
                'value' => 'light',
                'label' => 'Light',
            ),
            array(
                'value' => 'dark',
                'label' => 'Dark',
            )
        );
    }
}