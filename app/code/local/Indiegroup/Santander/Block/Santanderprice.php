<?php

class Indiegroup_Santander_Block_Santanderprice extends Mage_Catalog_Block_Product_Abstract {

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

    /**
     * Getting the Calculator URL
     * If this is set, add the price and format
     * Get the data from the URL and return it
     * @param string $price
     * @return null|SimpleXMLElement
     */
    public function getXml($price) {
        $calculatorUrl = Mage::getStoreConfig('santander/general/calculator_url');
        if($calculatorUrl != null) {
            $url = $calculatorUrl . $price . '&format=xml';
            $url = trim($url);
            if (($response_xml_data = file_get_contents($url)) === false) {
                Mage::log('Error fetching XML', null, 'santander.log');
                return null;
            } else {
                libxml_use_internal_errors(true);
                $data = simplexml_load_string($response_xml_data);
                if (!$data) {
                    Mage::log('Error loading XML', null, 'santander.log');
                    foreach (libxml_get_errors() as $error) {
                        Mage::log('$error->message', null, 'santander.log');
                        return null;
                    }
                } else {
                    return $data;
                }
            }
        } else {
            return null;
        }
    }

    /**
     * Getting the data from Santander by the getXml() function
     * if xml has data and calcSucces is true return the data otherwise return null
     * @param string $price
     * @return null|SimpleXMLElement
     */
    public function getDataOfSantander($price) {
        $xml = $this->getXml($price);
        if ($xml != null && $xml->calcSuccess == 'true') {
            return $xml;
        } else {
            return null;
        }
    }

    /**
     * Getting the Legal Notices from the Product
     * @return string
     */
    public function getLegalNotices() {
        $current_product = Mage::registry('current_product');
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $current_product->getSku());
        $wettelijkeVermelding = $product->getResource()->getAttribute('type_krediet')->getFrontend()->getValue($product);

        if ($wettelijkeVermelding == 'Reclame met vermelding van rentevoet') {
            return $this->getLegalNoticeRentevoet();
        }
        if ($wettelijkeVermelding == 'Reclame zonder vermelding van rentevoet') {
            return $this->getLegalNoticeZonderRentevoet();
        }
        if ($wettelijkeVermelding == 'Reclame voor promotionele kredieten') {
            return $this->getLegalNoticePromotioneleKred();
        }

    }

    /**
     * @return string
     */
    public function getLegalNoticeRentevoet() {
        return Mage::app()->getLayout()->createBlock('santander/reclamerentevoet')->setTemplate('santander/reclamerentevoet.phtml')->toHtml();
    }

    /**
     * @return string
     */
    public function getLegalNoticeZonderRentevoet() {
        return Mage::helper('santander')->__('Let op, geld lenen kost ook geld.');
    }

    /**
     * @return string
     */
    public function getLegalNoticePromotioneleKred() {
        return Mage::app()->getLayout()->createBlock('santander/reclamepromo')->setTemplate('santander/reclamepromo.phtml')->toHtml();
    }
}