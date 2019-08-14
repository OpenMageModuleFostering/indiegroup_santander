<?php
$installer = $this;
/**
 * Prepare database for install
 */
$installer->startSetup();

$status = Mage::getModel('sales/order_status');

$status->setStatus('santander_refer')->setLabel('Santander Refer')
    ->assignState(Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) //for example, use any available existing state
    ->save();

/**
 * Prepare database after install
 */
$installer->endSetup();