<?php

$installer = $this;
$installer->startSetup();

$installer->addAttribute("order", "santander_shipping_reserved", array("type" => "varchar"));
$installer->endSetup();

