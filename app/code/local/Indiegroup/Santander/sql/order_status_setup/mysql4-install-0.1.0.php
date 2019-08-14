<?php
$installer = $this;
/**
 * Prepare database for install
 */
$installer->startSetup();

$status = Mage::getModel('sales/order_status');

$status->setStatus('santander_reject')->setLabel('Santander Rejected')
    ->assignState(Mage_Sales_Model_Order::STATE_CANCELED) //for example, use any available existing state
    ->save();

/**
 * Prepare database after install
 */
$installer->endSetup();