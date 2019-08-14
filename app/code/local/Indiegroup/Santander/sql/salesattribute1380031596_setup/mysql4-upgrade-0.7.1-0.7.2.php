<?php

$installer = $this;
$installer->startSetup();

$installer->addAttribute("order", "santander_shipping_transaction_key", array("type" => "varchar"));
$installer->endSetup();

