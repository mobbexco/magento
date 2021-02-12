<?php

$installer = $this;
$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('mobbex/customfield'))
    ->addColumn('customfield_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'identity'  => true,
        'unsigned'  => true,
        'nullable'  => false,
        'primary'   => true,
        ), 'Id')
    ->addColumn('row_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
        'nullable'  => false,
        ), 'Row id')
    ->addColumn('object', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
        'nullable'  => false,
        ), 'Object')
    ->addColumn('field_name', Varien_Db_Ddl_Table::TYPE_VARCHAR, null, array(
        'nullable'  => false,
        ), 'Field name')
    ->addColumn('data', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
        'nullable'  => false,
        ), 'Data');

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
$installer->getConnection()->createTable($table);
$installer->endSetup();