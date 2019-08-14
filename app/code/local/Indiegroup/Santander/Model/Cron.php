<?php

class Indiegroup_Santander_Model_Cron {

    /**
     * Import Santander Prices
     *
     */
    public function importSantanderPrices() {
        // Check if santander is active
        if (Mage::getStoreConfig('santander/general/active')) {
            // Get all products
            $productCollection = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect('price')
                    ->addAttributeToSelect('special_price');
                    //->addAttributeToSelect('santanderactive');

            // Loop over products
            foreach ($productCollection as $_product) {
                // Set Santander Price by getting the price from Santander
                $price = $_product->getData('price');
                $specialPrice = $_product->getData('special_price');
                if ($specialPrice != null) {
                    $santanderPriceMonth = Mage::helper('santander')->getPricePerMonth($specialPrice);
                } else {
                    $santanderPriceMonth = Mage::helper('santander')->getPricePerMonth($price);
                }
                // Update product santanderpricemonth attribute
                Mage::getSingleton('catalog/product_action')->updateAttributes(array($_product->getEntityId()), array('santanderpricemonth' => "$santanderPriceMonth"), 1);
            }
        }
        // Send Email on completion of import
        $this->sendEmailOnComplete('All products prices have been imported from Santander', 'Santander Import Price');
    }

    /**
     * Send Email function
     * @param $body
     * @param $subject
     */
    public function sendEmailOnComplete($body, $subject) {
        try {
            $mail = Mage::getModel('core/email');
            $mail->setToName(Mage::getStoreConfig('santander/general/cron_name'));
            $mail->setToEmail(Mage::getStoreConfig('santander/general/cron_email'));
            $mail->setBody($body);
            $mail->setSubject($subject);
            $mail->setFromEmail(Mage::getStoreConfig('trans_email/ident_general/email'));
            $mail->setFromName(Mage::getStoreConfig('trans_email/ident_general/name'));
            $mail->setType('html');
            $mail->send();
            return $mail;
        } catch (Exception $e) {
            Mage::log('Mailproblem: '.$e, null, 'santander.log');
        }

    }

}