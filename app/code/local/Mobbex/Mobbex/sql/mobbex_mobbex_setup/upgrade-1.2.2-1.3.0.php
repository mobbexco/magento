<?php

$installer = $this;
$installer->startSetup();

$tableTransaction = $installer->getConnection()
    ->newTable($installer->getTable('mobbex/transaction'))
    ->addColumn('transaction_mobbex_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        ), 'Id')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        ), 'Order id')
    ->addColumn('data', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable'  => false,
        ), 'Data');

$installer->getConnection()->createTable($tableTransaction);            
$installer->endSetup();