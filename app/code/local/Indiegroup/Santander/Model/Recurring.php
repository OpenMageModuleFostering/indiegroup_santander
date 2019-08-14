<?php

class Indiegroup_Santander_Model_Recurring extends Mage_Payment_Model_Method_Abstract {

    protected $_code = 'santander_recurring';
    protected $_isInitializeNeeded = true;
    protected $_canUseInternal = true;
    protected $_canUseForMultishipping = false;

    /**
     * Get Order Redirect URL for an order placed with a recurring card
     * @return string
     */
    public function getOrderPlaceRedirectUrl() {

        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $quoteData = $quote->getData();
        $billingAddress = $quote->getBillingAddress();
        $billingAddressData = $billingAddress->getData();
        $customer = $quote->getCustomer();

        //Set Repeat Purchase Request
        $wsca = $this->setRepeatPurchaseRequest($quoteData, $billingAddress, $billingAddressData);
        //It returns a session ID that is needed for the redirect url so the form is correct
        return Mage::getUrl('santander/payment/redirect', array('_secure' => true, 'sid' => $wsca->setRepeatPurchaseReturn, 'recurring' => true));
    }

    /**
     * Setting the repeat purchase interface
     * Adding Data to Array and send it via SOAP to Santander
     * Getting back a session ID
     * This is so the form will be prefilled already in Santander
     * @param $quoteData
     * @param $billingAddress
     * @param $billingAddressData
     * @return mixed
     */
    public function setRepeatPurchaseRequest($quoteData, $billingAddress, $billingAddressData) {
        try {
            $customerId = $quoteData['customer_id'];
            if ($customerId != null) {
                $customerData = Mage::getModel('customer/customer')->load($customerId);
                $rrNumber = $customerData->getData('rr_number');
            } else {
                $rrNumber = null;
            }
            //Getting the SoapClient
            $soapClient = Mage::helper('santander')->getSoapClient();
            if ($soapClient != false) {
                $arrayValues = array();
                $arrayValues['password'] = Mage::getStoreConfig('santander/general/spassword'); //Adding Pass
                $arrayValues['userName'] = Mage::getStoreConfig('santander/general/susername'); //Adding Username
                //Adding Email
                $arrayValues['emailAddress'] = array(
                    'readonly' => true,
                    'value' => $quoteData['customer_email']
                );
                //Adding amount of order
                $arrayValues['amount'] = round($quoteData['grand_total'] * 100, 0);
                //Get BottomBanner
                $bottomBanner = Mage::getStoreConfig('santander/general/bottombanner');
                //If Bottombanner isset, then add it to array
                if ($bottomBanner != null) {
                    $arrayValues['bottomBanner'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, array('_secure' => true)) . 'theme' . DS . $bottomBanner;
                }
                //Get Reject Url
                $arrayValues['rejectUrl'] = Mage::getUrl('santander/payment/reject', array('_secure' => true));
                //Add Action Type
                // 0101 -> Check Credit Available
                // 0102 -> Reservation
                // 0103 -> Purchase
                // 0104 -> Purchase with pending period
                $arrayValues['actionType'] = '0101';
                //Getting CSS url
                $cssUrl = Mage::getStoreConfig('santander/general/css_url');
                //If CSS url is set in backend, add CSS url to Array
                if ($cssUrl != null) {
                    $arrayValues['cssUrl'] = $cssUrl;
                }
                //Getting Accept URL
                $arrayValues['acceptUrl'] = Mage::getUrl('santander/payment/accept', array('_secure' => true));
                //Getting Reference ID
                $arrayValues['referenceId'] = $quoteData['reserved_order_id'];
                //Add Postal Code
                $arrayValues['addressPostcode'] = array(
                    'readonly' => true,
                    'value' => $billingAddressData['postcode']
                );
                //Getting Topbanner from backend
                $topBanner = Mage::getStoreConfig('santander/general/topbanner');
                //If topbanner isset, then add it to array
                if ($topBanner != null) {
                    $arrayValues['topBanner'] = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA, array('_secure' => true)) . 'theme' . DS . $topBanner;
                }
                //If RRNUMBER is not null, then add it to the array
                if ($rrNumber != null) {
                    $arrayValues['rrNumber'] = array(
                        'readonly' => true,
                        'value' => $rrNumber
                    );
                }
                //Add refer url
                $arrayValues['referUrl'] = Mage::getUrl('santander/payment/refer', array('_secure' => true));
                $arrayData = array(
                    'wsca' => $arrayValues
                );
                //Setting the data via the SOAP to Santander, getting back a businessRequest
                //This is a session ID that is needed for the redirect url so the form is correct
                try {
                    $businessRequest = $soapClient->setRepeatPurchase(
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
