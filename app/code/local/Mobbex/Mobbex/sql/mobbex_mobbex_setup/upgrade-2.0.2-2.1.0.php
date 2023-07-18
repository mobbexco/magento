<?php

$this->startSetup();

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

$this->endSetup();