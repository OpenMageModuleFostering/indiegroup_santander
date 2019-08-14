<?php

class Indiegroup_Santander_Helper_Data extends Mage_Core_Helper_Abstract {

    /**
     * Make SoapClient Connection
     * @return bool|SoapClient
     */
    public function getSoapClient() {
        $wsdl = Mage::getStoreConfig('santander/general/wsdl');
        $webserviceAvailable = $this->checkWebservice($wsdl);
        if ($webserviceAvailable) {
            try {
                $soapClient = new SoapClient($wsdl, array('trace' => 1, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE));
            } catch (SoapFault $e) {
                $soapClient = false;
                Mage::log('SOAPCLIENT PROBLEM: '.$e, null, 'santander.log');
                $this->sendErrorMail($e);
            }
        } else {
            $soapClient = false;
        }

        return $soapClient;
    }

    /**
     * Make SoapClient Connection
     * @return bool|SoapClient
     */
    public function getSoapClientTransactions() {
        $wsdl = Mage::getStoreConfig('santander/general/wsdl_transaction');
        $webserviceAvailable = $this->checkWebservice($wsdl);
        if ($webserviceAvailable) {
            try {
                $soapClient = new SoapClient($wsdl, array('trace' => 1, 'exceptions' => true, 'cache_wsdl' => WSDL_CACHE_NONE));
            } catch (SoapFault $e) {
                $soapClient = false;
                Mage::log('SOAPCLIENT TRANSACTIONS PROBLEM: '.$e, null, 'santander.log');
                $this->sendErrorMail($e);
            }
        } else {
            $soapClient = false;
        }

        return $soapClient;
    }

    /**
     * Function to check if provided url is a webservice
     * and if the webservice is online
     * @param $url
     * @return bool
     */
    public function checkWebservice($url) {
        $handle = curl_init($url);
        $agent = "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)";
        curl_setopt ($handle, CURLOPT_URL,$url );
        curl_setopt($handle, CURLOPT_USERAGENT, $agent);
        curl_setopt ($handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($handle,CURLOPT_VERBOSE,false);
        curl_setopt($handle, CURLOPT_TIMEOUT, 5);
        curl_setopt($handle,CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($handle,CURLOPT_SSLVERSION,3);
        curl_setopt($handle,CURLOPT_SSL_VERIFYHOST, FALSE);
        $page=curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            /* You don't have a WSDL Service is down. exit the function */
            Mage::log('SOAPCLIENT PROBLEM: HTTP CODE '.$httpCode, null, 'santander.log');
            curl_close($handle);
            return false;
        }

        curl_close($handle);
        return true;
    }

    /**
     * Sending an error mail to the person set in the backend
     * Errors from the Webservice
     * @param $e
     */
    public function sendErrorMail($e) {
        $mail = Mage::getModel('core/email');
        $mail->setToName(Mage::getStoreConfig('santander/errors/send_to_name'));
        $mail->setToEmail(Mage::getStoreConfig('santander/errors/send_to_email'));
        $mail->setBody($e);
        $mail->setSubject('Error Webservice Santander');
        $mail->setFromEmail(Mage::getStoreConfig('santander/errors/send_from_email'));
        $mail->setFromName(Mage::getStoreConfig('santander/errors/send_from_name'));
        $mail->setType('html');

        try {
            $mail->send();
            return $mail;
        } catch (Exception $e) {
            Mage::log('Mail problem: '.$e, null, 'santander.log');
        }
    }

    /**
     * Get prices per month
     * @param $price
     * @return float|null
     */
    public function getPricePerMonth($price) {
        $xml = $this->getXml($price);
        if ($xml != null && $xml->calcSuccess == 'true') {
            $ranges = $xml->ranges->range;

            foreach ($ranges as $range) {
                if ($range->start == '1') {
                    return round((float) $range->installment, 2);
                }
            }
        } else {
            return null;
        }
    }

    /**
     * Getting the Calculator URL
     * If this is set, add the price and format
     * Get the data from the URL and return it
     * @param string $price
     * @return null|SimpleXMLElement
     */
    public function getXml($price) {
        $url = Mage::getStoreConfig('santander/general/calculator_url') . $price . '&format=xml';
        try {
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
        } catch (Exception $ex) {
            Mage::log('Error Fetching: ' . $ex, null, 'santander.log');
        }
    }

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
     * get Product Price HTML
     * setTemplate and render the view of the product price
     *
     * @param Mage_Catalog_Model_Product $_product
     * @return mixed
     */
    public function getProductPriceHtml($_product) {
        return Mage::getSingleton('core/layout')
            ->createBlock('santander/product_list')
            ->setTemplate('santander/list.phtml')
            ->setProduct($_product)
            ->toHtml();
    }

    /**
     * set Template and render the view of the total price in checkout
     * @param float $total
     * @return mixed
     */
    public function getTotalPriceHtml($total) {
        return Mage::getSingleton('core/layout')
            ->createBlock('santander/tax')
            ->setTemplate('santander/tax/grandtotal.phtml')
            ->setTotal($total)
            ->toHtml();
    }

    public function doSantanderReservation($soapClient, $params, $amount) {
        $reservationArrayValues = array();
        $reservationArrayValues['Password'] = Mage::getStoreConfig('santander/general/spassword'); //Adding Pass
        $reservationArrayValues['UserName'] = Mage::getStoreConfig('santander/general/susername'); //Adding Username
        $reservationArrayValues['CardNumber'] = str_replace('564188', '', $params['cardNumber']);
        $expMonth = '';
        if(strlen($params['expirationmonth']) === 1) {
            $expMonth = '0'.$params['expirationmonth'];
        } else {
            $expMonth = $params['expirationmonth'];
        }
        $reservationArrayValues['ExpiryDate'] = substr($params['expirationyear'], 2, 2).$expMonth; //Format: YYMM
        $reservationArrayValues['Amount'] = round($amount * 100, 0);
        $reservationArrayValues['CustomerTransactionKey'] = $this->generateRandomString(); //Unique per transaction -> Chosen by customer
        $reservationArrayValues['CreditType'] = '000'; //Agreement Codes
        $reservationArrayValues['TransactionType'] = '2'; // What Transaction Type -> Reservation
        $reservationArrayValues['RRNumber'] = $params['rrnumber']; // RRNumber
        $reservationArrayValues['PostalCode'] = $params['postcode']; // PostalCode
        $reservationArrayValues['TransactionGroupKey'] = $params['referenceNumber']; // TransactionGroupKey

        $reservationArray = array(
            'transaction' => $reservationArrayValues
        );
        try {
            $businessRequest = $soapClient->SendTransaction(
                $reservationArray
            );
        } catch (Exception $e) {
            Mage::log('Error during doSantanderProductReservation of Santander: '.$e, null, 'santander.log');
        }
        $result = $businessRequest->SendTransactionResult;
        return $result;
    }

    public function doSantanderCancellation($soapClient, $params, $amount, $originalTransactionKey) {
        $cancelReservationArrayValues = array();
        $cancelReservationArrayValues['Password'] = Mage::getStoreConfig('santander/general/spassword'); //Adding Pass
        $cancelReservationArrayValues['UserName'] = Mage::getStoreConfig('santander/general/susername'); //Adding Username
        $cancelReservationArrayValues['CardNumber'] = str_replace('564188', '', $params['cardNumber']);
        $cancelReservationArrayValues['Amount'] = round($amount * 100, 0);
        $cancelReservationArrayValues['CustomerTransactionKey'] = $this->generateRandomString(); //Unique per transaction -> Chosen by customer
        $cancelReservationArrayValues['CreditType'] = '000'; //Agreement Codes
        $cancelReservationArrayValues['TransactionType'] = '3'; // What Transaction Type -> Cancel Reservation
        $cancelReservationArrayValues['TransactionGroupKey'] = $params['referenceNumber']; // TransactionGroupKey
        $cancelReservationArrayValues['OriginalUserName'] = Mage::getStoreConfig('santander/general/susername');
        $cancelReservationArrayValues['OriginalCustomerTransactionKey'] = $originalTransactionKey;
        //$expMonth = '';
        //if(strlen($params['expirationmonth']) === 1) {
        //    $expMonth = '0'.$params['expirationmonth'];
        //} else {
        //    $expMonth = $params['expirationmonth'];
        //}
        //$cancelReservationArrayValues['ExpiryDate'] = substr($params['expirationyear'], 2, 2).$expMonth; //Format: YYMM
        $cancelReservationArrayValues['ExpiryDate'] = $params['expirationmonth'];
        $cancelReservationArrayValues['PostalCode'] = $params['postcode'];
        $cancelReservationArrayValues['RRNumber'] = $params['rrnumber'];

        $cancelReservationArray = array(
            'transaction' => $cancelReservationArrayValues
        );

        try {
            $businessRequest = $soapClient->SendTransaction(
                $cancelReservationArray
            );
        } catch (Exception $e) {
            Mage::log('Error during doSantanderCancelReservationFullAmount of Santander: '.$e, null, 'santander.log');
        }

        $result = $businessRequest->SendTransactionResult;
        return $result;
    }

    public function doSantanderPurchase($soapClient, $params, $amount) {
        $purchaseArrayValues = array();
        $purchaseArrayValues['Password'] = Mage::getStoreConfig('santander/general/spassword'); //Adding Pass
        $purchaseArrayValues['UserName'] = Mage::getStoreConfig('santander/general/susername'); //Adding Username
        $purchaseArrayValues['CardNumber'] = str_replace('564188', '', $params['cardNumber']);

        $purchaseArrayValues['ExpiryDate'] = $params['expirationmonth']; //Format: YYMM
        $purchaseArrayValues['Amount'] = round($amount * 100, 0);
        $purchaseArrayValues['CustomerTransactionKey'] = $this->generateRandomString(); //Unique per transaction -> Chosen by customer
        $purchaseArrayValues['CreditType'] = '000'; //Agreement Codes
        $purchaseArrayValues['TransactionType'] = '4'; // What Transaction Type -> Reservation
        $purchaseArrayValues['RRNumber'] = $params['rrnumber']; // RRNumber
        $purchaseArrayValues['PostalCode'] = $params['postcode']; // PostalCode
        $purchaseArrayValues['TransactionGroupKey'] = $params['referenceNumber']; // TransactionGroupKey

        $purchaseArray = array(
            'transaction' => $purchaseArrayValues
        );
        try {
            $businessRequest = $soapClient->SendTransaction(
                $purchaseArray
            );
        } catch (Exception $e) {
            Mage::log('Error during doSantanderPurchase of Santander: '.$e, null, 'santander.log');
        }
        $result = $businessRequest->SendTransactionResult;
        return $result;
    }

    public function generateRandomString() {
        return md5(microtime());
    }

}

