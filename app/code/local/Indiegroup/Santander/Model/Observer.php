<?php

class Indiegroup_Santander_Model_Observer
{

    /**
     * On product save, get the price per month from Santander
     * Save it to the product
     * @param $observer
     */
    public function catalog_product_save_after($observer)
    {
        $_product = $observer->getProduct();
        $specialPrice = null;

        if($_product->getTypeId() == 'grouped') {
            $arrayPrices = array();

            $associatedProducts = $_product->getTypeInstance(true)->getAssociatedProducts($_product);
            foreach($associatedProducts as $associatedProduct) {
                $arrayPrices[] = round($associatedProduct->getPrice(), 2);
            }
            asort($arrayPrices);
            $price = array_shift($arrayPrices);
        } else {
            if($_product->getTypeId() == 'bundle') {
                //Zend_Debug::dump($_product->getData());exit;
                $selectionCollection = $_product->getTypeInstance(true)->getSelectionsCollection(
                    $_product->getTypeInstance(true)->getOptionsIds($_product), $_product
                );

                foreach($selectionCollection as $option) {
                    $bundled_prices[]=$option->getPrice();
                }

                sort($bundled_prices);

                $min_price=$bundled_prices[0];
                $price = $min_price;
            } else {
                $price = $_product->getData('price');
                $specialPrice = $_product->getData('special_price');
            }

        }


        if ($specialPrice != null) {
            $santanderPriceMonth = Mage::helper('santander')->getPricePerMonth($specialPrice);
        } else {
            $santanderPriceMonth = Mage::helper('santander')->getPricePerMonth($price);
        }
        Mage::getSingleton('catalog/product_action')->updateAttributes(array($_product->getEntityId()), array('santanderpricemonth' => "$santanderPriceMonth"), 1);
    }

    public function sales_order_payment_cancel($observer)
    {
        $order = $observer->getPayment()->getOrder();
        if ($order->getData('santander_full_reserved') === '1') {
            $soapClient = Mage::helper('santander')->getSoapClientTransactions();
            if ($soapClient != false) {
                $params = array();
                //$payment = $order->getPayment();
                $params['cardNumber'] = $order->getData('santander_card_number');
                $params['referenceNumber'] = $order->getIncrementId();
                $params['postcode'] = $order->getBillingAddress()->getData('postcode');
                $params['rrnumber'] = $order->getData('santander_rr_number');
                $params['expirationmonth'] = $order->getData('santander_expdate');
                $result = Mage::helper('santander')->doSantanderCancellation($soapClient, $params, $order->getData('base_grand_total'), $order->getSantanderTransactionKey());
                $violations = $result->Violations;
                $violations = (array)$violations;
            }
            try {
                if (count($violations) == 0) {
                    $order->setData('santander_full_reserved', 0);
                    $order->addStatusHistoryComment('Cancelling of reservation of order Amount has been done');
                    $order->save();
                } else {
                    foreach ($violations as $violation) {
                        Mage::log('Error during doSantanderCancelReservationFullAmount of Santander: ' . $violation->ErrorMessage . ', ' . $violation->ErrorNumber, null, 'santander.log');
                    }
                    $order->addStatusHistoryComment('Error during doSantanderCancelReservationFullAmount of Santander');
                    $order->save();
                }

            } catch (Mage_Core_Exception $e) {
                Mage::log('Error during doSantanderCancelReservationFullAmount of Santander: ' . $e, null, 'santander.log');
                $order->addStatusHistoryComment('Catch: Error during doSantanderCancelReservationFullAmount of Santander');
                $order->save();
            }
            Mage::log('Full Cancellation of order', null, 'santandercancel.log');
        }
        if ($order->getData('santander_full_reserved') === '0') {
            $items = $order->getAllItems();
            $soapClient = Mage::helper('santander')->getSoapClientTransactions();
            if ($soapClient != false) {
                foreach ($items as $item) {
                    if ($item->getData('santander_item_reserved') === '1') {
                        $params = array();
                        //$payment = $order->getPayment();
                        $params['cardNumber'] = $order->getData('santander_card_number');
                        $params['referenceNumber'] = $order->getIncrementId();
                        $params['postcode'] = $order->getBillingAddress()->getData('postcode');
                        $params['rrnumber'] = $order->getData('santander_rr_number');
                        $params['expirationmonth'] = $order->getData('santander_expdate');
                        $result = Mage::helper('santander')->doSantanderCancellation($soapClient, $params, ($item->getQtyOrdered() * $item->getPrice()), $item->getData('santander_transaction_key'));
                        $violations = $result->Violations;
                        $violations = (array)$violations;
                        try {
                            if (count($violations) == 0) {
                                $item->setData('santander_item_reserved', 0);
                                $order->addStatusHistoryComment('Cancelling of reservation of item Amount has been done');
                            } else {
                                foreach ($violations as $violation) {
                                    Mage::log('Error during doSantanderCancelReservationProductAmount of Santander: ' . $violation->ErrorMessage . ', ' . $violation->ErrorNumber, null, 'santander.log');
                                }
                                $order->addStatusHistoryComment('Error during doSantanderCancelReservationProductAmount of Santander');
                                $order->save();
                            }

                        } catch (Mage_Core_Exception $e) {
                            Mage::log('Error during doSantanderCancelReservationProductAmount of Santander: ' . $e, null, 'santander.log');
                            $order->addStatusHistoryComment('Catch: Error during doSantanderCancelReservationProductAmount of Santander');
                            $order->save();
                        }
                        Mage::log('Cancellation of order item', null, 'santandercancel.log');
                    }

                }

                if ($order->getData('santander_shipping_reserved') === '1') {
                    $shippingAmount = $order->getShippingAmount();
                    $params = array();
                    //$payment = $order->getPayment();
                    $params['cardNumber'] = $order->getData('santander_card_number');
                    $params['referenceNumber'] = $order->getIncrementId();
                    $params['postcode'] = $order->getBillingAddress()->getData('postcode');
                    $params['rrnumber'] = $order->getData('santander_rr_number');
                    $params['expirationmonth'] = $order->getData('santander_expdate');
                    $result = Mage::helper('santander')->doSantanderCancellation($soapClient, $params, $shippingAmount, $order->getData('santander_shipping_transaction_key'));
                    $violations = $result->Violations;
                    $violations = (array)$violations;
                    try {
                        if (count($violations) == 0) {
                            $order->setData('santander_shipping_reserved', 0);
                            $order->addStatusHistoryComment('Cancelling of reservation of shipping Amount has been done');
                        } else {
                            foreach ($violations as $violation) {
                                Mage::log('Error during doSantanderCancelReservationProductAmount of Santander: ' . $violation->ErrorMessage . ', ' . $violation->ErrorNumber, null, 'santander.log');
                            }
                            $order->addStatusHistoryComment('Error during doSantanderCancelReservationProductAmount of Santander');
                            $order->save();
                        }

                    } catch (Mage_Core_Exception $e) {
                        Mage::log('Error during doSantanderCancelReservationProductAmount of Santander: ' . $e, null, 'santander.log');
                        $order->addStatusHistoryComment('Catch: Error during doSantanderCancelReservationProductAmount of Santander');
                        $order->save();
                    }
                    Mage::log('Cancellation of order shipping amount', null, 'santandercancel.log');
                }
                $order->save();
            }
        }
        Mage::log('sales_order_payment_cancel');
    }

    public function sales_order_shipment_save_after($observer)
    {
        $order = $observer->getEvent()->getShipment()->getOrder();
        if ($order->getData('santander_full_reserved') === '1') {
            Mage::throwException('Order cannot be shipped, still waiting for feedback from Santander');
        }
        if ($order->getData('santander_full_reserved') === '0') {
            $items = $order->getAllItems();
            foreach ($items as $item) {
                if ($item->getData('santander_item_reserved') === '0') {
                    Mage::throwException('Order cannot be shipped, still waiting for feedback from Santander');
                }
            }
            if ($order->getData('santander_shipping_reserved') === '0') {
                Mage::throwException('Order cannot be shipped, still waiting for feedback from Santander');
            }

            //EERST CANCELLEN VAN DE RESERVATIES
            $soapClient = Mage::helper('santander')->getSoapClientTransactions();
            if ($soapClient != false) {
                foreach ($items as $item) {
                    if ($item->getData('santander_item_reserved') === '1') {
                        $params = array();
                        //$payment = $order->getPayment();
                        $params['cardNumber'] = $order->getData('santander_card_number');
                        $params['referenceNumber'] = $order->getIncrementId();
                        $params['postcode'] = $order->getBillingAddress()->getData('postcode');
                        $params['rrnumber'] = $order->getData('santander_rr_number');
                        $params['expirationmonth'] = $order->getData('santander_expdate');
                        //Zend_Debug::dump($item->getData('santander_transaction_key'));exit;
                        $result = Mage::helper('santander')->doSantanderCancellation($soapClient, $params, ($item->getQtyOrdered() * $item->getPrice()), $item->getData('santander_transaction_key'));
                        $violations = $result->Violations;
                        $violations = (array)$violations;
                        try {
                            if (count($violations) == 0) {
                                $item->setData('santander_item_reserved', 0);
                                $order->addStatusHistoryComment('Cancelling of reservation of item Amount has been done');
                            } else {
                                foreach ($violations as $violation) {
                                    Mage::log('Error during doSantanderCancelReservationProductAmount of Santander: ' . $violation->ErrorMessage . ', ' . $violation->ErrorNumber, null, 'santander.log');
                                }
                                $order->addStatusHistoryComment('Error during doSantanderCancelReservationProductAmount of Santander');
                                $order->save();
                            }

                        } catch (Mage_Core_Exception $e) {
                            Mage::log('Error during doSantanderCancelReservationProductAmount of Santander: ' . $e, null, 'santander.log');
                            $order->addStatusHistoryComment('Catch: Error during doSantanderCancelReservationProductAmount of Santander');
                            $order->save();
                        }
                        Mage::log('Cancellation of order item', null, 'santandercancel.log');
                    }
                }
                if ($order->getData('santander_shipping_reserved') === '1') {
                    $shippingAmount = $order->getShippingAmount();
                    $params = array();
                    //$payment = $order->getPayment();
                    $params['cardNumber'] = $order->getData('santander_card_number');
                    $params['referenceNumber'] = $order->getIncrementId();
                    $params['postcode'] = $order->getBillingAddress()->getData('postcode');
                    $params['rrnumber'] = $order->getData('santander_rr_number');
                    $params['expirationmonth'] = $order->getData('santander_expdate');
                    $result = Mage::helper('santander')->doSantanderCancellation($soapClient, $params, $shippingAmount, $order->getData('santander_shipping_transaction_key'));
                    $violations = $result->Violations;
                    $violations = (array)$violations;
                    try {
                        if (count($violations) == 0) {
                            $order->setData('santander_shipping_reserved', 0);
                            $order->addStatusHistoryComment('Cancelling of reservation of shipping Amount has been done');
                        } else {
                            foreach ($violations as $violation) {
                                Mage::log('Error during doSantanderCancelReservationProductAmount of Santander: ' . $violation->ErrorMessage . ', ' . $violation->ErrorNumber, null, 'santander.log');
                            }
                            $order->addStatusHistoryComment('Error during doSantanderCancelReservationProductAmount of Santander');
                            $order->save();
                        }

                    } catch (Mage_Core_Exception $e) {
                        Mage::log('Error during doSantanderCancelReservationProductAmount of Santander: ' . $e, null, 'santander.log');
                        $order->addStatusHistoryComment('Catch: Error during doSantanderCancelReservationProductAmount of Santander');
                        $order->save();
                    }
                    Mage::log('Cancellation of order shipping amount', null, 'santandercancel.log');
                }
                //NU DE PURCHASE GAAN DOEN VAN HET ORDER
                $params = array();
                //$payment = $order->getPayment();
                $params['cardNumber'] = $order->getData('santander_card_number');
                $params['referenceNumber'] = $order->getIncrementId();
                $params['postcode'] = $order->getBillingAddress()->getData('postcode');
                $params['rrnumber'] = $order->getData('santander_rr_number');
                $params['expirationmonth'] = $order->getData('santander_expdate');
                $result = Mage::helper('santander')->doSantanderPurchase($soapClient, $params, $order->getGrandTotal());
                $violations = $result->Violations;
                $violations = (array)$violations;
                try {
                    if (count($violations) == 0) {
                        $order->setData('santander_purchased', 1);
                        $order->addStatusHistoryComment('Purchase of total Amount has been done');
                    } else {
                        foreach ($violations as $violation) {
                            Mage::log('Error during doSantanderPurchase of Santander: ' . $violation->ErrorMessage . ', ' . $violation->ErrorNumber, null, 'santander.log');
                        }
                        $order->addStatusHistoryComment('Error during doSantanderPurchase of Santander');
                        $order->save();
                    }

                } catch (Mage_Core_Exception $e) {
                    Mage::log('Error during doSantanderPurchase of Santander: ' . $e, null, 'santander.log');
                    $order->addStatusHistoryComment('Catch: Error during doSantanderPurchase of Santander');
                    $order->save();
                }
                Mage::log('Purchase of total Amount of order', null, 'santandercancel.log');
            }
        }
        Mage::log('sales_order_shipment_save_after');
    }

}