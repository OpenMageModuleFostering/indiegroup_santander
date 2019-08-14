<?php

$installer = $this;

$installer->startSetup();

$attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection')->load();
foreach ($attributeSetCollection as $id=>$attributeSet) {
    $installer->addAttributeGroup('catalog_product' , $id , 'Santander');
}

$setup = new Mage_Eav_Model_Entity_Setup('core_setup');

$attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection')->load();

foreach ($attributeSetCollection as $id=>$attributeSet) {
    $attributeGroupId=$setup->getAttributeGroupId('catalog_product', $id, 'Santander');

    $attributeId=$setup->getAttributeId('catalog_product', 'santanderpricemonth');
    $attributeId2=$setup->getAttributeId('catalog_product', 'type_krediet');

    $setup->addAttributeToSet($entityTypeId='catalog_product',$id, $attributeGroupId, $attributeId);
    $setup->addAttributeToSet($entityTypeId='catalog_product',$id, $attributeGroupId, $attributeId2);
}

$installer->endSetup();