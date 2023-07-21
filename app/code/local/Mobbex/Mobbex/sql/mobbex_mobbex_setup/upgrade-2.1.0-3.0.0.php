<?php

$this->startSetup();

// Stops execution if dependencies cannot be found
if (!file_exists(__DIR__ . '/../../vendor/autoload.php'))
    throw new \Exception(sprintf(
        'Error: Bad installation of Mobbex module. Re-download the module (%s) from releases page on Github (%s).',
        'mobbex.{version}.mage-1.6-1.9.zip',
        'https://github.com/mobbexco/magento/releases/'
    ), 1);

//install tables from sdk sql
foreach (['cache'] as $table) {
    $this->run(
        str_replace(
            'DB_PREFIX_',
            (string) \Mage::getConfig()->getTablePrefix(),
            file_get_contents(__DIR__ . "/../../vendor/mobbexco/php-plugins-sdk/src/sql/$table.sql")
        )
    );
}

// Try to create tables
$this->run(
    str_replace(
        'DB_PREFIX_',
        (string) \Mage::getConfig()->getTablePrefix(),
        file_get_contents(dirname(__FILE__) . '/install.sql')
    )
);

// Update < 1.4 transaction table
if (!$this->getConnection()->tableColumnExists('mobbex_transaction', 'parent'))
    $this->run(
        str_replace(
            'DB_PREFIX_',
            (string) \Mage::getConfig()->getTablePrefix(),
            file_get_contents(dirname(__FILE__) . '/alter.sql')
        )
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

// Add childs column if doesn´t exists
if (!$this->getConnection()->tableColumnExists('mobbex_transaction', 'childs'))
    $this->run(
        "ALTER TABLE `mobbex_transaction` ADD COLUMN `childs` TEXT NOT NULL;"
    );

$this->endSetup();