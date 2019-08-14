<?php

$installer = $this;

$installer->startSetup();

$installer->addAttribute('catalog_product', 'santanderpricemonth', array(
    'type' => 'varchar',
    'backend' => '',
    'frontend' => '',
    'label' => 'Santander Price Per Month',
    'input' => 'text',
    'class' => '',
    'source' => '',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible' => true,
    'required' => false,
    'user_defined' => false,
    'default' => '',
    'searchable' => false,
    'filterable' => false,
    'comparable' => false,
    'visible_on_front' => false,
    'unique' => false,
    'apply_to' => '',
    'is_configurable' => false,
));

$installer->addAttribute('catalog_product', 'type_krediet', array(
    'type' => 'varchar',
    'backend' => '',
    'frontend' => '',
    'label' => 'Santander Type Krediet',
    'input' => 'select',
    'class' => '',
    'source' => '',
    'global' => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
    'visible' => true,
    'required' => false,
    'user_defined' => false,
    'default' => '',
    'searchable' => false,
    'filterable' => false,
    'comparable' => false,
    'visible_on_front' => false,
    'unique' => false,
    'apply_to' => '',
    'is_configurable' => false,
    'option'     => array (
        'values' => array(
            0 => 'Reclame met vermelding van rentevoet',
            1 => 'Reclame zonder vermelding van rentevoet',
            2 => 'Reclame voor promotionele kredieten',
        )
    ),
));

$installer->endSetup();