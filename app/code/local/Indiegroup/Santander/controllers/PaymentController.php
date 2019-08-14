<?php

class Indiegroup_Santander_PaymentController extends Mage_Core_Controller_Front_Action {

    /**
     * This is the action to set the template for the redirect page
     * The redirect action is triggered when someone places an order
     */
    public function redirectAction() {
        $this->loadLayout();
        $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'santander', array('template' => 'santander/redirect.phtml'));
        $this->getLayout()->getBlock('content')->append($block);
        $this->renderLayout();
    }

    /**
     * This is the action when the return from santander is called by
     * /santander/payment/reject
     *
     * When reject action has increment id
     * We get the order to fill the cart again, because order is failed
     * Then we cancel the order and add message that payment has been rejected
     */
    public function rejectAction() {
        if($this->getRequest()->getParams()) {
            $referenceId = $this->getRequest()->getParam('referenceId');
            if($referenceId != null) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($referenceId);
                $cart = Mage::getSingleton('checkout/cart');

                $items = $order->getItemsCollection();
                foreach ($items as $item) {
                    try {
                        $cart->addOrderItem($item,$item->getQty());
                    }
                    catch (Mage_Core_Exception $e){
                        if (Mage::getSingleton('checkout/session')->getUseNotice(true)) {
                            Mage::getSingleton('checkout/session')->addNotice($e->getMessage());
                        }
                        else {
                            Mage::getSingleton('checkout/session')->addError($e->getMessage());
                        }
                    }
                    catch (Exception $e) {
                        Mage::getSingleton('checkout/session')->addException($e,
                            Mage::helper('checkout')->__('Cannot add the item to shopping cart.')
                        );
                    }
                }
                try {
                    $cart->save();
                    if(!$order->canCancel())
                        continue;
                    $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Santander Reject')->addStatusToHistory('santander_reject', 'Santander Reject', false)->save();
                } catch (Mage_Core_Exception $e) {
                    Mage::log('Error during reject Action of Santander: '.$e, null, 'santander.log');
                }
            }
        }
        Mage::getSingleton('core/session')->addError($this->__('Your payment has been rejected.'));
        $this->_redirect('checkout/cart');
    }

    /**
     * This is the action when the return from santander is called by
     * /santander/payment/accept
     *
     * When accept action has increment id
     * We get the order and add Accept status to history of order
     * We redirect to success page
     */
    public function acceptAction() {
        if($this->getRequest()->getParams()) {
            $referenceId = $this->getRequest()->getParam('referenceId');
            if($referenceId != null) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($referenceId);
                try {
                    $order->addStatusHistoryComment('Order has been Accepted by Santander');
                    $order->save();
                } catch (Mage_Core_Exception $e) {
                    Mage::log('Error during accept Action of Santander: '.$e, null, 'santander.log');
                }
            }
        }
        Mage::getSingleton('core/session')->addSuccess($this->__('Your payment has been accepted.'));
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * This is the action when the return from santander is called by
     * /santander/payment/refer
     *
     * When refer action has increment id
     * We get the order and add Refer status to history of order
     * We redirect to success page
     */
    public function referAction() {
        if($this->getRequest()->getParams()) {
            $referenceId = $this->getRequest()->getParam('referenceId');
            if($referenceId != null) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($referenceId);
                try {
                    $order->addStatusHistoryComment('Order has been Referred by Santander');
                    $order->save();
                } catch (Mage_Core_Exception $e) {
                    Mage::log('Error during refer Action of Santander: '.$e, null, 'santander.log');
                }
            }
        }
        Mage::getSingleton('core/session')->addSuccess($this->__('Your payment is refered.'));
        $this->_redirect('checkout/onepage/success');
    }

    /**
     * This is the action when santander updates store via
     * /santander/payment/status
     *
     * If result is Accept:
     * STATUSSES:
     * 1 = Temporary OK
     * This mean that a new credit application was accepted, but not yet booked.
     * The state of the order in Magento should be pending until you receive a new update with state 2.
     * When you receive this state 1, you can only send in transactions type 02 (= reservations).
     * You could do this just to make sure that the amount is reserved on the account of the customer, but this is not really necessary.
     * 2 = OK Stage 2 Transfer
     * This means that for a new credit application we have received the signed contract and it has been booked.
     * For the retailer this mean that he can proceed the processing of the order, so you can send us the transactions.
     * 3 = OK repeatPurchase applications
     * This means that the customer has sufficient available on his account and you can immediately proceed to process the transactions.
     *
     * So in fact for each state you could invoke the transaction webservices.
     * The only thing that you should keep in mind is that you can only send us transactions type 04 (purchase), the moment the goods are shipped.
     * So most retailers will always perform the transactions in the following sequence:
     * 1.       Reservation total basket amount
     * 2.       Cancellation reservation total basket amount
     * 3.       Reservation per article in the basket
     * 4.       Cancellation per article in the basket
     * 5.       Purchase per article in the basket (only when the state of the POST is 2 or 3 and the goods are shipped)
     *
     * Santander Post to the store and adds data
     */
    public function statusAction() {

        //If data is posted to url
        if ($this->getRequest()->isPost()) {
            $params = $this->getRequest()->getPost(); // Get Paramaters
            Mage::log($params, null, 'santanderparams.log');
            $userName = Mage::getStoreConfig('santander/general/susername'); // Get Username
            if($params['userName'] === $userName) {
                //Check if Username is the same
                // If result is Reject
                if($params['result'] == 'Reject') {
                    // Getting order by reference Number
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($params['referenceNumber']);
                    // Call The Reject Order Action
                    $this->noValidTransaction($order, $params, 'Order has been rejected!', 'Order '. $params['referenceNumber']. ' has been rejected!');
                }
                // If result is Refer
                if($params['result'] == 'Refer') {
                    // Getting order by reference Number
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($params['referenceNumber']);
                    // Getting order total
                    $orderTotal = $order->getData('base_grand_total');
                    // Check order total with param amount
                    if($params['amount'] >= $orderTotal) {
                        // Call the Refer Order Action
                        $this->referOrderAction($order, $params);
                    } else {
                        // Amount of param is not greater then order total
                        // Reject the Order
                        // Call the Reject Order Action
                        $this->noValidTransaction($order, $params, 'Order Amount is greater then Santander amount, rejected!', 'Order '. $params['referenceNumber']. ' : Amount is greater then Santander amount, rejected!');

                    }
                }
                // If result is Accept
                if($params['result'] == 'Accept') {
                    // Check if acceptStatus 1
                    // Getting order by reference Number
                    $order = Mage::getModel('sales/order');
                    $order->loadByIncrementId($params['referenceNumber']);
                    // Getting order total
                    $orderTotal = $order->getData('base_grand_total');
                    // Getting Billing Address
                    $billingAddress = $order->getBillingAddress();
                    // Getting Postal Code
                    $postcode = $billingAddress->getData('postcode');
                    // Getting Email
                    $email = $order->getData('customer_email');
                    $validTransaction = false;

                    $emailValid = $this->checkIfValidAction('email', $params, $email);
                    $postcodeValid = $this->checkIfValidAction('postcode', $params, $postcode);
                    $amountValid = $this->checkIfValidAction('amount', $params, $orderTotal);
                    // Check if email is equal
                    if($emailValid) {
                        // Check if postal code is equal
                        if($postcodeValid) {
                            // Check if amount is equal
                            if($amountValid) {
                                // Everything Ok, then call Accept Order Action with Reservation
                                if($params['acceptStatus'] === '1') {
                                    $this->acceptOrderReservationAction($order, $params);
                                }
                                if($params['acceptStatus'] === '2' || $params['acceptStatus'] === '3') {
                                    $this->acceptOrderAction($order, $params);
                                }

                                $validTransaction = true;
                            } else {
                                // Not OK: Order Amount not equal, so Reject Order
                                $message = 'Order Amount is greater then Santander amount, rejected!';
                                $logMessage = 'Order '. $params['referenceNumber']. ' : Amount is greater then Santander amount, rejected!';
                            }
                        } else {
                            // Not OK: Postal Code not equal, so Reject Order
                            $message = 'Postcode is not valid, rejected!';
                            $logMessage = 'Order '. $params['referenceNumber']. ' : Postcode is not valid, rejected!';
                        }
                    } else {
                        // Not OK: Email not equal, so Reject Order
                        $message = 'Email is not valid, rejected!';
                        $logMessage = 'Order '. $params['referenceNumber']. ' : Email is not valid, rejected!';
                    }
                    if(!$validTransaction) {
                        $this->noValidTransaction($order, $params, $message, $logMessage);
                    }
                }
            } else {
                Mage::log($this->__('Order '. $params['referenceNumber']. ' : Username is incorrect'), null, 'santander.log');
            }
        }
    }

    /**
     * Reject Order if nog Valid
     * @param $order
     * @param $params
     * @param $message
     * @param $logMessage
     */
    public function noValidTransaction($order, $params, $message, $logMessage) {
        $this->rejectOrderAction($order, $params, $message);
        Mage::log($logMessage, null, 'santander.log');
    }

    /**
     * Validation check passed on cases
     * @param $case
     * @param $params
     * @param $field
     * @return bool
     */
    public function checkIfValidAction($case, $params, $field) {
        switch($case) {
            case 'email':
                if(strtolower($params['emailaddress']) === $field) {
                    return true;
                }
            case 'postcode':
                if($params['postcode'] === $field) {
                    return true;
                }
            case 'amount':
                if($params['amount'] >= $field) {
                    return true;
                }
            default:
                return false;
        }
    }

    /**
     * Reject the Order (called from statusAction)
     * @param $order
     * @param $params
     * @param null $message
     */
    public function rejectOrderAction($order, $params, $message = null) {
        //SAVE RRNUMBER TO CUSTOMER
        $customerId = $order->getData('customer_id');
        if($customerId != null) {
            $customerData = Mage::getModel('customer/customer')->load($customerId);
            $customerData->setData('rr_number', $params['rrnumber']);
            $customerData->save();
        }

        if($message == null) {
            $message = 'Payment Rejected by Santander';
        }
        // Cancel order
        $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message)->addStatusToHistory('santander_reject', $message, false)->save();

    }

    /**
     * Refer the order (called from statusAction)
     * @param $order
     * @param $params
     */
    public function referOrderAction($order, $params) {
        //SAVE RRNUMBER TO CUSTOMER
        $customerId = $order->getData('customer_id');
        if($customerId != null) {
            $customerData = Mage::getModel('customer/customer')->load($customerId);
            $customerData->setData('rr_number', $params['rrnumber']);
            $customerData->save();
        }
        $order->addStatusToHistory('santander_refer', 'Payment Refer by Santander', false)->save();

    }

    /**
     * Accept the Order (Stage 1) (Called from statusAction)
     * Do reservation of the whole amount
     * Transactiontypes:
     * 0 -> Standard Purchase
     * 1 -> Standard Refund
     * 2 -> Reservation
     * 3 -> Cancel Reservation
     * 4 -> Purchase
     * 5 -> Neg. Purchase
     * 6 -> Purchase Refund
     * 7 -> Neg. Purch. Refund
     * 8 -> Neg. Reservation
     * 9 -> Cancel Neg. Reserv.
     * 10 -> Canc. Purch. Refund
     * @param Mage_Sales_Model_Order $order
     * @param array() $params
     */
    public function acceptOrderReservationAction($order, $params) {
        try {

            $order->setData('santander_card_number', $params['cardNumber']);
            $order->setData('santander_rr_number', $params['rrnumber']);
            if(strlen($params['expirationmonth']) === 1) {
                $expMonth = '0'.$params['expirationmonth'];
            } else {
                $expMonth = $params['expirationmonth'];
            }
            $expDate = substr($params['expirationyear'], 2, 2).$expMonth; //Format: YYMM
            $order->setData('santander_expdate', $expDate);
            $order->save();
            $this->doSantanderReservationFullAmount($order, $params, $params['acceptStatus']);
        }
            catch (Mage_Core_Exception $e) {
                Mage::log('Error during acceptOrderReservationAction of Santander: '.$e, null, 'santander.log');
        }
    }

    /**
     * Accept the Order (Stage 2 or 3) (Called from statusAction)
     * Do further transactions for the order
     * Transactiontypes:
     * 0 -> Standard Purchase
     * 1 -> Standard Refund
     * 2 -> Reservation
     * 3 -> Cancel Reservation
     * 4 -> Purchase
     * 5 -> Neg. Purchase
     * 6 -> Purchase Refund
     * 7 -> Neg. Purch. Refund
     * 8 -> Neg. Reservation
     * 9 -> Cancel Neg. Reserv.
     * 10 -> Canc. Purch. Refund
     * @param Mage_Sales_Model_Order $order
     * @param array() $params
     */
    public function acceptOrderAction($order, $params) {
        $santanderCancelReservation = false;
        try {
            //TODO: Check if order isn't cancelled/invoiced/shipped yet
            $order->setData('santander_card_number', $params['cardNumber']);
            $order->setData('santander_rr_number', $params['rrnumber']);
            if(strlen($params['expirationmonth']) === 1) {
                $expMonth = '0'.$params['expirationmonth'];
            } else {
                $expMonth = $params['expirationmonth'];
            }
            $expDate = substr($params['expirationyear'], 2, 2).$expMonth; //Format: YYMM
            $order->setData('santander_expdate', $expDate);
            $order->save();

            $santanderFullReserved = $order->getData('santander_full_reserved');

            if($santanderFullReserved === '1') {
                // DO CANCEL FULL RESERVATION
                $santanderCancelReservation = $this->doSantanderCancelReservationFullAmount($order, $params, $params['acceptStatus']);

                if($santanderCancelReservation) {
                    // DO PER PRODUCT RESERVATION
                    $this->doSantanderProductReservation($order, $params, $params['acceptStatus']);
                    // DO INVOICING OF ORDER
                }

            } else {
                // DO FULL RESERVATION
                $this->doSantanderReservationFullAmount($order, $params, $params['acceptStatus']);
                // DO CANCEL FULL RESERVATION
                $santanderCancelReservation = $this->doSantanderCancelReservationFullAmount($order, $params, $params['acceptStatus']);

                if($santanderCancelReservation) {
                    // DO PER PRODUCT RESERVATION
                    $this->doSantanderProductReservation($order, $params, $params['acceptStatus']);
                    // DO INVOICING OF ORDER
                }
            }
        }
        catch (Mage_Core_Exception $e) {
            Mage::log('Error during accept order Action of Santander: '.$e, null, 'santander.log');
        }


    }

    /**
     * @param $order
     * @param $params
     * @param $acceptStatus
     */
    public function doSantanderReservationFullAmount($order, $params, $acceptStatus) {
        $soapClient = Mage::helper('santander')->getSoapClientTransactions();
        if ($soapClient != false) {
            $result = Mage::helper('santander')->doSantanderReservation($soapClient, $params, $params['amount']);
            $violations = $result->Violations;
            $violations = (array) $violations;
        }
        try {
            if(count($violations) == 0) {
                if($acceptStatus == 2 || $acceptStatus == 3) {
                    $order->setData('santander_full_reserved', 1);
                    $order->setData('santander_transaction_key', $result->CustomerTransactionKey);

                    $order->addStatusHistoryComment('Order has been accepted by Santander, reservation of order Amount has been done');
                    $order->save();
                }
                if($acceptStatus == 1) {
                    $order->setData('santander_accept_status', $params['acceptStatus']);
                    $order->setData('santander_full_reserved', 1);
                    $order->setData('santander_transaction_key', $result->CustomerTransactionKey);

                    $order->addStatusHistoryComment('Order has been accepted by Santander, reservation of order Amount has been done');
                    $order->addStatusToHistory('santander_accept', 'Payment Accepted by Santander (Stage 1)', false);
                    $order->save();
                }
            } else {
                foreach($violations as $violation) {
                    Mage::log('Error during doSantanderReservationFullAmount of Santander: '.$violation->ErrorMessage . ', '.$violation->ErrorNumber, null, 'santander.log');
                }
            }

        } catch (Mage_Core_Exception $e) {
            Mage::log('Error during doSantanderReservationFullAmount of Santander: '.$e, null, 'santander.log');
        }
    }

    public function doSantanderCancelReservationFullAmount($order, $params, $acceptStatus) {
        $soapClient = Mage::helper('santander')->getSoapClientTransactions();
        if ($soapClient != false) {
            $result = Mage::helper('santander')->doSantanderCancellation($soapClient, $params, $params['amount'], $order->getSantanderTransactionKey());
            $violations = $result->Violations;
            $violations = (array) $violations;
        }
        $status = false;
        try {
            if(count($violations) == 0) {
                if($acceptStatus == 2 || $acceptStatus == 3) {
                    $order->setData('santander_full_reserved', 0);
                    $order->addStatusHistoryComment('Cancelling of reservation of order Amount has been done');
                    $order->save();
                    $status = true;
                } else {
                    Mage::log('Error during doSantanderCancelReservationFullAmount of Santander: Acceptstatus is wrong', null, 'santander.log');
                    $order->addStatusHistoryComment('Error during doSantanderCancelReservationFullAmount of Santander');
                    $order->save();
                }
            } else {
                foreach($violations as $violation) {
                    Mage::log('Error during doSantanderCancelReservationFullAmount of Santander: '.$violation->ErrorMessage . ', '.$violation->ErrorNumber, null, 'santander.log');
                }
                $order->addStatusHistoryComment('Error during doSantanderCancelReservationFullAmount of Santander');
                $order->save();
            }

        } catch (Mage_Core_Exception $e) {
            Mage::log('Error during doSantanderCancelReservationFullAmount of Santander: '.$e, null, 'santander.log');
            $order->addStatusHistoryComment('Catch: Error during doSantanderCancelReservationFullAmount of Santander');
            $order->save();
        }
        return $status;
    }

    /**
     * @param $order
     * @param $params
     * @param $acceptStatus
     */
    public function doSantanderProductReservation($order, $params, $acceptStatus) {
        $soapClient = Mage::helper('santander')->getSoapClientTransactions();
        if ($soapClient != false) {
            $items = $order->getAllItems();
            foreach($items as $item) {
                $result = Mage::helper('santander')->doSantanderReservation($soapClient, $params, ($item->getQtyOrdered() * $item->getPrice()));
                $violations = $result->Violations;
                $violations = (array) $violations;

                try {
                    if(count($violations) == 0) {
                        if($acceptStatus == 2 || $acceptStatus == 3) {
                            $item->setData('santander_transaction_key', $result->CustomerTransactionKey);
                            $item->setData('santander_item_reserved', 1);

                            $order->addStatusHistoryComment('Order has been accepted by Santander, reservation of item has been done');

                        } else {
                            Mage::log('Error during doSantanderProductReservation of Santander: Acceptstatus is wrong', null, 'santander.log');
                        }
                    } else {
                        foreach($violations as $violation) {
                            Mage::log('Error during doSantanderProductReservation of Santander: '.$violation->ErrorMessage . ', '.$violation->ErrorNumber, null, 'santander.log');
                        }
                    }

                } catch (Mage_Core_Exception $e) {
                    Mage::log('Error during doSantanderProductReservation of Santander: '.$e, null, 'santander.log');
                }
            }
            /**
             * Reserve shipping amount
             */
            $shippingAmount = $order->getShippingAmount();
            $result = $result = Mage::helper('santander')->doSantanderReservation($soapClient, $params, $shippingAmount);
            $violations = $result->Violations;
            $violations = (array) $violations;

            try {
                if(count($violations) == 0) {
                    if($acceptStatus == 2 || $acceptStatus == 3) {
                        $order->setData('santander_shipping_transaction_key', $result->CustomerTransactionKey);
                        $order->setData('santander_shipping_reserved', 1);

                        $order->addStatusHistoryComment('Order has been accepted by Santander, reservation of shipping amount has been done');

                    } else {
                        Mage::log('Error during doSantanderProductReservation of Santander: Acceptstatus is wrong', null, 'santander.log');
                    }
                } else {
                    foreach($violations as $violation) {
                        Mage::log('Error during doSantanderProductReservation of Santander: '.$violation->ErrorMessage . ', '.$violation->ErrorNumber, null, 'santander.log');
                    }
                }

            } catch (Mage_Core_Exception $e) {
                Mage::log('Error during doSantanderProductReservation of Santander: '.$e, null, 'santander.log');
            }
            /**
             * END Reserve shipping amount
             */
            $order->save();

        }

    }

    public function generateRandomString() {
        return md5(microtime());
    }

}
