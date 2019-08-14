<?php

$installer = $this;
$installer->startSetup();

$installer->addAttribute("order_item", "santander_item_reserved", array("type" => "varchar"));
$installer->endSetup();