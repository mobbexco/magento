<?php

$this->startSetup();

// Stops execution if dependencies cannot be found
if (!file_exists(__DIR__ . '/../../vendor/autoload.php'))
    throw new \Exception(sprintf(
        'Error: Bad installation of Mobbex module. Re-download the module (%s) from releases page on Github (%s).',
        'mobbex.{version}.mage-1.6-1.9.zip',
        'https://github.com/mobbexco/magento/releases/'
    ), 1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Rename transaction id column
if ($this->getConnection()->isTableExists('mobbex_transaction'))
    if ($this->getConnection()->tableColumnExists('mobbex_transaction', 'transaction_mobbex_id'))
        $this->run(
            "ALTER TABLE `mobbex_transaction` RENAME COLUMN transaction_mobbex_id TO id;"
        );

// Rename customfield id column
if ($this->getConnection()->isTableExists('mobbex_customfield'))
    if ($this->getConnection()->tableColumnExists('mobbex_customfield', 'customfield_id'))
        $this->run(
            "ALTER TABLE `mobbex_customfield` RENAME COLUMN customfield_id TO id;"
        );

// Create and alter tables
foreach (['cache', 'transaction', 'log', 'task', 'customfield'] as $table)
    new \Mobbex\Model\Table(
        $table,
        $table == 'customfield' ? \Mobbex\Model\Table::getTableDefinition('custom_fields') : null
    );

// Insert authorized status
if (!$this->getTableRow('sales/order_status', 'status', 'authorized_mobbex'))
    $this->getConnection()->insertArray($this->getTable('sales/order_status'), ['status', 'label'],
        [
            [
                'status' => 'authorized_mobbex',
                'label'  => 'Authorized (Mobbex)',
            ]
        ]
    );

// Insert states
if (!$this->getTableRow('sales/order_status_state', 'status', 'authorized_mobbex'))
    $this->getConnection()->insertArray($this->getTable('sales/order_status_state'), ['status', 'state', 'is_default'],
        [
            [
                'status'     => 'authorized_mobbex',
                'state'      => 'authorized_mobbex_state',
                'is_default' => 1
            ]
        ]
    );

$this->endSetup();