<?php

$installer = $this;
$installer->startSetup();

$installer->addAttribute("order_item", "santander_transaction_key", array("type" => "varchar"));
$installer->endSetup();