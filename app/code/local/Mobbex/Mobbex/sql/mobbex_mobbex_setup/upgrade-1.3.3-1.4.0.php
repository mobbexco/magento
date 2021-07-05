<?php

$installer = $this;
$installer->startSetup();

/**
 * Adding Extra Column to sales_flat_quote_address
 * to store the delivery instruction field
 */
$sales_quote_address = $installer->getTable('sales/quote_address');
$installer->getConnection()
    ->addColumn($sales_quote_address, 'mobbex_dni', array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'comment' => 'Table Comment'
    ));

/**
 * Adding Extra Column to sales_flat_order_address
 * to store the delivery instruction field
 */
$sales_order_address = $installer->getTable('sales/order_address');
$installer->getConnection()
    ->addColumn($sales_order_address, 'mobbex_dni', array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'comment' => 'Table Comment'
    ));

$installer->endSetup();