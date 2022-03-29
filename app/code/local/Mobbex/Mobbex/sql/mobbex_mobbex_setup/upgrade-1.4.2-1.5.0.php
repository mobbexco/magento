<?php

$installer = $this;
$installer->startSetup();

if($installer->tableExists('mobbex_transaction')){

    $installer->run("
    ALTER TABLE {$this->getTable('mobbex/transaction')}
    ADD COLUMN `parent` TINYINT NOT NULL,
    ADD COLUMN `operation_type` TEXT NOT NULL,
    ADD COLUMN `payment_id` TEXT NOT NULL,
    ADD COLUMN `description` TEXT NOT NULL,
    ADD COLUMN `status_code` TEXT NOT NULL,
    ADD COLUMN `status_message` TEXT NOT NULL,
    ADD COLUMN `source_name` TEXT NOT NULL,
    ADD COLUMN `source_type` TEXT NOT NULL,
    ADD COLUMN `source_reference` TEXT NOT NULL,
    ADD COLUMN `source_number` TEXT NOT NULL,
    ADD COLUMN `source_expiration` TEXT NOT NULL,
    ADD COLUMN `source_url` TEXT NOT NULL,
    ADD COLUMN `source_installment` TEXT NOT NULL,
    ADD COLUMN `installment_name` TEXT NOT NULL,
    ADD COLUMN `installment_amount` TEXT NOT NULL,
    ADD COLUMN `installment_count` TEXT NOT NULL,
    ADD COLUMN `cardholder` TEXT NOT NULL,
    ADD COLUMN `entity_name` TEXT NOT NULL,
    ADD COLUMN `entity_uid` TEXT NOT NULL,
    ADD COLUMN `customer` TEXT NOT NULL,
    ADD COLUMN `checkout_uid` TEXT NOT NULL,
    ADD COLUMN `total` DECIMAL(18,2) NOT NULL,
    ADD COLUMN `currency` TEXT NOT NULL,
    ADD COLUMN `risk_analysis` TEXT NOT NULL,
    ADD COLUMN `created` TEXT NOT NULL,
    ADD COLUMN `updated` TEXT NOT NULL;
    ");

$installer->endSetup();

} else {

    $tableTransaction = $installer->getConnection()
        ->newTable($installer->getTable('mobbex/transaction'))
        ->addColumn('transaction_mobbex_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
            ), 'Id')
        ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Order id')
        ->addColumn('parent', Varien_Db_Ddl_Table::TYPE_BOOLEAN, null, array(
            'nullable'  => false,
            ), 'Parent')
        ->addColumn('operation_type', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Operation type')
        ->addColumn('payment_id', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Payment id')
        ->addColumn('description', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Description')
        ->addColumn('status_code', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Status code')
        ->addColumn('status_message', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Status message')
        ->addColumn('source_name', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Source name')
        ->addColumn('source_type', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Source type')
        ->addColumn('source_reference', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Source reference')
        ->addColumn('source_number', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Source number')
        ->addColumn('source_expiration', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Source expiration')
        ->addColumn('source_url', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Source url')
        ->addColumn('source_installment', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Source installment')
        ->addColumn('installment_name', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Installment name')
        ->addColumn('installment_amount', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Installment amount')
        ->addColumn('installment_count', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Installment count')
        ->addColumn('cardholder', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Cardholder')
        ->addColumn('entity_name', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Entity name')
        ->addColumn('entity_uid', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Entity uid')
        ->addColumn('customer', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Customer')
        ->addColumn('checkout_uid', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Checkout uid')
        ->addColumn('total', Varien_Db_Ddl_Table::TYPE_DECIMAL, '18,2', array(
            'nullable'  => false,
            ), 'Total')
        ->addColumn('currency', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Currency')
        ->addColumn('risk_analysis', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
            ), 'Risk analysis')
        ->addColumn('data', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
        ), 'Data')
        ->addColumn('created', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
        ), 'Created')
        ->addColumn('updated', Varien_Db_Ddl_Table::TYPE_TEXT, null, array(
            'nullable'  => false,
        ), 'Updated');

        $installer->getConnection()->createTable($tableTransaction);            
        $installer->endSetup();
}



