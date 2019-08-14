<?php

$installer = $this;
$installer->startSetup();

$installer->addAttribute("order", "santander_accept_status", array("type" => "varchar"));
$installer->addAttribute("order", "santander_full_reserved", array("type" => "int"));
$installer->endSetup();

