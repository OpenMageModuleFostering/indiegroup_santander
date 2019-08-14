<?php

class Indiegroup_Santander_Block_Reclamepromo extends Mage_Core_Block_Template {

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
     * @param string $id
     * @return null|SimpleXMLElement
     */
    public function getDataOfSantander($id) {
        $_product = Mage::getModel('catalog/product')->load($id);
        if($_product->getSpecialPrice() == "") {
            $price = round($_product->getPrice(), 2);
        } else {
            $price = round($_product->getSpecialPrice(), 2);
        }
        $xml = $this->getXml($price);
        if($xml != null && $xml->calcSuccess == 'true') {
            return $xml;
        } else {
            return null;
        }
    }
}