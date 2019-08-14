<?php

$installer = $this;
$installer->startSetup();

$installer->addAttribute("order", "santander_card_number", array("type" => "varchar"));
$installer->addAttribute("order", "santander_rr_number", array("type" => "varchar"));
$installer->addAttribute("order", "santander_expdate", array("type" => "varchar"));
$installer->endSetup();

