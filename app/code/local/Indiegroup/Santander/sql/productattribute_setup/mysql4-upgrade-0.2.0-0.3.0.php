<?php

$installer = $this;
$installer->startSetup();
$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

if (!function_exists('updateAttribute')) {
    function updateAttribute(Mage_Eav_Model_Entity_Setup $setup, $entityTypeId, $code, $key, $value)
    {
        $id = $setup->getAttribute($entityTypeId, $code, 'attribute_id');
        $setup->updateAttribute($entityTypeId, $id, $key, $value);
    }
}

updateAttribute($setup, 'catalog_product', 'santanderpricemonth', 'used_in_product_listing' ,'1');

$installer->endSetup();