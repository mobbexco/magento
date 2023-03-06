<?php

class Mobbex_Mobbex_PaymentController extends Mage_Core_Controller_Front_Action
{

    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function responseAction()
    {
        $orderId = $this->getRequest()->getParam('orderId');
        $status = $this->getRequest()->getParam('status');

        $order = Mage::getModel('sales/order');
        $order->loadByIncrementId($orderId);

        Mage::log($this->getRequest()->getParams(), null, 'mobbex_response.log', true);
        Mage::log($order, null, 'mobbex_response.log', true);

        // Success or Waiting: Results must be received with Webhook
        if ($status > 1 && $status < 400) {
            $this->_redirect('checkout/onepage/success', array('_secure' => true));
        } else {
            // Restore last order
            if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
                if ($lastQuoteId = Mage::getSingleton('checkout/session')->getLastQuoteId()) {
                    $quote = Mage::getModel('sales/quote')->load($lastQuoteId);
                    $quote->setIsActive(true)->save();
                }

                // Send error message
                Mage::getSingleton('core/session')->addError(Mage::helper('mobbex')->__('The payment has failed.'));

                //Redirect to cart
                $this->_redirect('checkout/cart', array('_secure' => true));
            }
        }
    }

    public function notificationAction()
    {
        try {
            // Get Data
            $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $this->getRequest()->getPost();
            $orderId  = $this->getRequest()->getParam('orderId');

            // Load the Order
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($orderId);

            $res = Mage::getModel('mobbex/transaction')->formatWebhookData($postData['data'], $orderId, (Mage::getStoreConfig('payment/mobbex/multicard') == true), Mage::getStoreConfig('payment/mobbex/multivendor'));

            //Execute own hook to extend functionalities
            Mage::helper('mobbex/data')->executeHook('mobbexWebhookReceived', false, $postData['data'], $order);

            // Get the Reference ( Transaction ID )
            $transaction_id = $res['payment_id'];

            // Get the Status
            $status  = $res['status_code'];
            $message = $res['status_message'] . ' ( Transacción: ' . $transaction_id . ' )';

            //Save transaction information
            Mage::getModel('mobbex/transaction')->saveMobbexTransaction($res);

            //Return if the webhook is not parent
            if ($res['parent'] == false) {
                return;
            }

            // Exit if it is a expired operation and the order has already been paid
            if ($status == 401 && $order->getTotalPaid() > 0)
                return;

            //Debug the response data
            Mage::helper('mobbex/data')->debug("Processing Webhook Data", compact('orderId', 'res'));

            if (isset($orderId) && !empty($status)) {

                $source_type = $res['source_type'];
                $source_name = $res['source_name'];

                // Get Source number in case of cards
                $source_number = 'N/A';
                if (!empty($res['source_number'])) {
                    $source_number = ' ' . $res['source_number'];
                }

                $user_name  = $res['user']['name'];
                $user_email = $res['user']['email'];

                Mage::helper('mobbex/data')->debug('Saving state for order: ', $order->getId());

                $paymentComment = 'Método de pago: ' . $source_name . '. Número: ' . $source_number;
                $userComment = 'Pago realizado por: ' . $user_name . ' - ' . $user_email;

                $order->addStatusHistoryComment($paymentComment);
                $order->addStatusHistoryComment($userComment);

                $statusName = $this->getStatusName($order, $status);

                // Get Order status
                if ($statusName == 'inProcess') {
                    $order->setStatus(Mage::getStoreConfig('payment/mobbex/order_status_in_process'));
                } else if ($statusName === 'Approved') {

                    //Uncancel order if is cancelled
                    $items = $order->getAllItems();
                    if ($items[0]->getStatus() == 'Canceled') {
                        $order->setBaseDiscountCanceled(0);
                        $order->setBaseShippingCanceled(0);
                        $order->setBaseSubtotalCanceled(0);
                        $order->setBaseTaxCanceled(0);
                        $order->setBaseTotalCanceled(0);
                        $order->setDiscountCanceled(0);
                        $order->setShippingCanceled(0);
                        $order->setSubtotalCanceled(0);
                        $order->setTaxCanceled(0);
                        $order->setTotalCanceled(0);

                        foreach ($items as $item) {
                            $item->setQtyCanceled(0);
                            $item->setTaxCanceled(0);
                            $item->setHiddenTaxCanceled(0);
                            $item->save();
                        }
                    }

                    //set order status
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                    $order->setStatus(Mage::getStoreConfig('payment/mobbex/order_status_approved'));

                    // Prepare payment object
                    $payment = $order->getPayment();
                    $payment->setTransactionId($transaction_id);
                    $payment->setLastTransId($transaction_id);
                    $payment->setIsTransactionClosed(1);
                    $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array(
                        'source' => $source_name,
                        'source_number' => $source_number,
                    ));

                    // Save payment, transaction and order
                    $payment->save();

                    // Create invoice if not exists
                    if (!$order->hasInvoices()) {
                        $invoice = $order->prepareInvoice()
                            ->register()
                            ->capture()
                            ->addComment($message, 1, 1)
                            ->save();
    
                        $order->addRelatedObject($invoice);
                    }

                    // Send notifications to the user
                    $order->sendNewOrderEmail();
                    $order->setEmailSent(true);
                } else if ($statusName === 'Refunded') {
                    // Cancel Sale
                    $order->cancel()->setStatus(Mage::getStoreConfig('payment/mobbex/order_status_refunded'));
                } else {
                    $order->cancel()->setState(Mage::getStoreConfig('payment/mobbex/order_status_cancelled'), true, $message);
                }

                Mage::helper('mobbex/data')->debug('Save Order: ', $order->getId());

                // Save the order
                $order->save();

                Mage::getSingleton('checkout/session')->unsQuoteId();
            }
        } catch (Exception $e) {
            Mage::helper('mobbex/data')->debug('Exception: ', $e, true);
        }
    }

    // The cancel action is triggered when an order is to be cancelled
    public function cancelAction()
    {
        if (Mage::getSingleton('checkout/session')->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId(Mage::getSingleton('checkout/session')->getLastRealOrderId());
            if ($order->getId()) {
                // Flag the order as 'cancelled' and save it
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
            }
        }

        Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => true));
    }

    /** Use to get checkout data via ajax */
    public function getCheckoutAction()
    {
        // Retrieve order
        $_order = new Mage_Sales_Model_Order();
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = $_order->loadByIncrementId($orderId);
        // Get Checkout Data
        $checkout = Mage::helper('mobbex/data')->createCheckout($order);

        $mobbex_data['returnUrl']  = isset($checkout['return_url']) ? $checkout['return_url'] : '';
        $mobbex_data['checkoutId'] = isset($checkout['id']) ? $checkout['id'] : '';
        $mobbex_data['orderId']    = $orderId;
        $mobbex_data['url']        = isset($checkout['url']) ? $checkout['url'] : '';
        $mobbex_data['wallet']     = isset($checkout['wallet']) ? $checkout['wallet'] : '';

        // Return data in json
        $this->getResponse()->clearHeaders()->setHeader(
            'Content-type',
            'application/json'
        );
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode($mobbex_data)
        );
    }

    /**
     * Get the status config name from transaction status code.
     * 
     * @param OrderInterface $order
     * @param int $statusCode
     * 
     * @return string 
     */
    public function getStatusName($order, $statusCode)
    {
        if ($statusCode == 2 || $statusCode == 3 || $statusCode == 100 || $statusCode == 201) {
            $name = 'InProcess';
        } else if ($statusCode == 4 || $statusCode >= 200 && $statusCode < 400) {
            $name = 'Approved';
        } else {
            $name = $order->getStatus() != 'pending' ? 'Cancelled' : 'Refunded';
        }

        return $name;
    }
}