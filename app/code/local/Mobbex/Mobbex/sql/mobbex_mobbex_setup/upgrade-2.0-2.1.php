<?php

$installer = $this;
$installer->startSetup();

// Insert statuses
$installer->getConnection()->insertArray(
    $installer->getTable('sales/order_status'),
    array(
        'status',
        'label'
    ),
    array(
        array('status' => 'authorized_mobbex', 'label' => 'Authorized (Mobbex)'),
    )
);

// Insert states and mapping of statuses to states
$installer->getConnection()->insertArray(
    $installer->getTable('sales/order_status_state'),
    array(
        'status',
        'state',
        'is_default'
    ),
    array(
        array(
            'status' => 'authorized_mobbex',
            'state' => 'authorized_mobbex_state',
            'is_default' => 1
        )
    )
);
