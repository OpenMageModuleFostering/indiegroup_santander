<?php

$installer = $this;
$installer->startSetup();

$installer->addAttribute("order", "santander_purchased", array("type" => "int"));
$installer->endSetup();

