<?php

class Indiegroup_Santander_Block_Product_List extends Mage_Core_Block_Template {

    /**
     * Check if Santander Extension is Active
     * Returning True/False
     * If Config is not set return false
     * @return bool
     */
    public function isActive() {
        $isActive = Mage::getStoreConfig('santander/general/active');
        if($isActive) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if Show Price per Month is set
     * Returning True/False
     * If Config is not set return false
     * @return bool
     */
    public function showPricePerMonth() {
        $prijsPerMaand = Mage::getStoreConfig('santander/general/prijs_maand');
        if($prijsPerMaand) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Getting the Price Per Month from the product from attribute santanderpricemonth
     * @param Mage_Catalog_Model_Product $_product
     * @return string
     */
    public function getPricePerMonth($_product) {
        $price = $_product->getResource()->getAttribute('santanderpricemonth')->getFrontend()->getValue($_product);
        return Mage::helper('core')->currency(round($price, 2));
    }

}
