<?php

class Indiegroup_Santander_Model_Standard extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'santander_new';
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = false;

    /**
     * Get Order Redirect URL for an order placed with a new card
     * @return string
     */
    public function getOrderPlaceRedirectUrl() {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quoteData = $quote->getData();
        $billingAddress = $quote->getBillingAddress();
        $billingAddressData = $billingAddress->getData();

        //Set New Purchase Request
        $wsca = $this->setNewBusinessRequest($quoteData, $billingAddressData, $billingAddress);
        //It returns a session ID that is needed for the redirect url so the form is correct
        return Mage::getUrl('santander/payment/redirect', array('_secure' => true, 'sid' => $wsca->setNewBusinessReturn, 'recurring' => false));
    }

    /**
     * Setting the new card purchase interface
     * Adding Data to Array and send it via SOAP to Santander
     * Getting back a session ID
     * This is so the form will be prefilled already in Santander
     * @param $quoteData
     * @param $billingAddressData
     * @param $billingAddress
     * @return mixed
     */
    public function setNewBusinessRequest($quoteData, $billingAddressData, $billingAddress) {
        try {
            //Getting SOAPCLIENT
            $soapClient = Mage::helper('santander')->getSoapClient();
            if ($soapClient != false) {
                $arrayValues = array();
                $arrayValues['password'] = Mage::getStoreConfig('santander/general/spassword'); //Set password
                $arrayValues['userName'] = Mage::getStoreConfig('santander/general/susername'); //Set Username
                //Set Email
                $arrayValues['emailAddress'] = array(
                    'readonly' => true,
                    'value' => $quoteData['customer_email']
                );
                //Set amount of order
                $arrayValues['amount'] = round($quoteData['grand_total'] * 100, 0);
                //Getting bottom banner from backend
                $bottomBanner = Mage::getStoreConfig('santander/general/bottombanner');
                //If isset, then add it to array
                if ($bottomBanner != null) {
                    $arrayValues['bottomBanner'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, array('_secure' => true)) . 'theme' . DS . $bottomBanner;
                }
                //Set Reject URL
                $arrayValues['rejectUrl'] = Mage::getUrl('santander/payment/reject', array('_secure' => true));
                //Set Action Type, should always be 0101 (Affordability Check)
                $arrayValues['actionType'] = '0101';
                //Getting CSS url from backend
                $cssUrl = Mage::getStoreConfig('santander/general/css_url');
                //IF isset then add it to array
                if ($cssUrl != null) {
                    $arrayValues['cssUrl'] = $cssUrl;
                }
                //Set accept URL
                $arrayValues['acceptUrl'] = Mage::getUrl('santander/payment/accept', array('_secure' => true));
                //Set reference ID
                $arrayValues['referenceId'] = $quoteData['reserved_order_id'];
                //Set Postal Code
                $arrayValues['addressPostcode'] = array(
                    'readonly' => true,
                    'value' => $billingAddressData['postcode']
                );
                //Getting top banner from backend
                $topBanner = Mage::getStoreConfig('santander/general/topbanner');
                //If isset, then add it to array
                if ($topBanner != null) {
                    $arrayValues['topBanner'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, array('_secure' => true)) . 'theme' . DS . $topBanner;
                }

                $number = explode(" ", $billingAddress->getStreet(1));

                $street = str_replace(end($number), "", $billingAddress->getStreet(1));
                if (preg_match('~[0-9]~', end($number))) {
                    $streetNumber = end($number);
                }
                //Set House Number
                if ($billingAddress->getStreet(2)) {
                    $arrayValues['addressHouseNumber'] = array(
                        'readonly' => true,
                        'value' => $billingAddress->getStreet(2)
                    );
                } else {
                    $arrayValues['addressHouseNumber'] = array(
                        'readonly' => true,
                        'value' => $streetNumber
                    );
                }
                //Set Country ID
                $arrayValues['addressCountryType'] = array(
                    'readonly' => true,
                    'value' => $billingAddressData['country_id']
                );
                //Set Name
                $arrayValues['surname'] = array(
                    'readonly' => true,
                    'value' => $billingAddressData['lastname']
                );
                //Set First Name
                $arrayValues['firstName'] = array(
                    'readonly' => true,
                    'value' => $billingAddressData['firstname']
                );
                //Set Street
                $arrayValues['addressStreet'] = array(
                    'readonly' => true,
                    'value' => $street
                );
                //Set Refer Url
                $arrayValues['referUrl'] = Mage::getUrl('santander/payment/refer', array('_secure' => true));
                $arrayData = array(
                    'wsca' => $arrayValues
                );
                //Setting the data via the SOAP to Santander, getting back a businessRequest
                //This is a session ID that is needed for the redirect url so the form is correct
                try {
                    $businessRequest = $soapClient->setNewBusiness(
                            $arrayData
                    );
                } catch (Exception $e) {
                    Mage::log($e);
                }
            }
            return $businessRequest;
        } catch (Exception $e) {
            Mage::getSingleton('core/session')->addError(Mage::helper('santander')->__('Something went wrong, please try again.'));
            $this->_redirect('checkout/cart');
        }
    }

}
