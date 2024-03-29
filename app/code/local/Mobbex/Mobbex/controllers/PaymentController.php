<?php

class Mobbex_Mobbex_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        parent::preDispatch();
        // Init class properties
        \Mage::helper('mobbex/instantiator')->setProperties($this, ['sdk', 'settings', 'customField', 'helper', 'logger', 'mobbexTransaction', '_order', '_checkoutSession', '_quote']);
    }   
    
    // The response action is triggered when your gateway sends back a response after processing the customer's payment
    public function responseAction()
    {
        try {
            
            //debug
            $this->logger->log('debug', 'Payment Controller > responseAction | Params: ', $this->getRequest()->getParams());
            //get params
            extract($this->getRequest()->getParams());
            //load order
            $this->_order->loadByIncrementId($orderId);

            // Success or Waiting: Results must be received with Webhook
            if ($status > 1 && $status < 400) {
                $this->_redirect('checkout/onepage/success', array('_secure' => true));
            } else {
                // Restore last order
                if ($this->_checkoutSession->getLastRealOrderId()) {

                    if ($lastQuoteId = $this->_checkoutSession->getLastQuoteId()) {
                        $quote = $this->_quote->load($lastQuoteId);
                        $quote->setIsActive(true)->save();
                    }

                    // Send error message
                    $this->logger->log('error', 'The payment has failed');

                    //Redirect to cart
                    $this->_redirect('checkout/cart', array('_secure' => true));
                }
            }

        } catch (\Exception $e) {
            $this->logger->log('error', 'Payment Controller > responseAction | ' . $e->getMessage());   
        }

    }

    public function notificationAction()
    {
        try {
            // Get Data
            $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $this->getRequest()->getPost();
            // Get order id and token from query param
            $orderId  = $this->getRequest()->getParam('orderId');
            $token    = $this->getRequest()->getParam('mbbxToken');

            if (!\Mobbex\Repository::validateToken($token))
                throw new \Exception("Invalid Token: $token", 1);

            // Load the Order
            $this->_order->loadByIncrementId($orderId);

            $res = Mage::getModel('mobbex/transaction')->formatWebhookData($postData['data'], $orderId);

            //Execute own hook to extend functionalities
            $this->helper->executeHook('mobbexWebhookReceived', false, $postData['data'], $this->_order);

            // Get the Reference ( Transaction ID )
            $transaction_id = $res['payment_id'];

            // Get the Status
            $status  = $res['status_code'];
            $message = $res['status_message'] . ' ( Transacción: ' . $transaction_id . ' )';

            //Save transaction information
            $this->mobbexTransaction->saveMobbexTransaction($res);

            //Return if the webhook is not parent
            if ($res['parent'] == false)
                return;

            // Exit if it is a expired operation and the order has already been paid
            if ($status == 401 && $this->_order->getTotalPaid() > 0)
                return;

            //Debug the response data
            $this->logger->log("debug", "Payment Controller > notificationAction | Processing Webhook Data: ", compact('orderId', 'res'));

            if (isset($orderId) && !empty($status)) {

                
                // Get Payment Sources
                $source_name = $res['source_name'];
                $source_number = 'N/A';
                if (!empty($res['source_number']))
                    $source_number = ' ' . $res['source_number'];

                $user_name  = $res['user']['name'];
                $user_email = $res['user']['email'];

                $this->logger->log("debug", "Payment Controller > notificationAction | Saving state for order: " . $this->_order->getId());

                $paymentComment = 'Método de pago: ' . $source_name . '. Número: ' . $source_number;
                $userComment = 'Pago realizado por: ' . $user_name . ' - ' . $user_email;

                $this->_order->addStatusHistoryComment($paymentComment);
                $this->_order->addStatusHistoryComment($userComment);

                $statusName = $this->getStatusName($this->_order, $status);

                // Get Order status
                if ($statusName == 'inProcess') {
                    $this->_order->setStatus($this->settings->get('order_status_in_process'));
                } else if ($statusName === 'approved') {

                    //Uncancel order if is cancelled
                    $items = $this->_order->getAllItems();
                    
                    if ($items[0]->getStatus() == 'Canceled') {
                        $this->_order->setBaseDiscountCanceled(0);
                        $this->_order->setBaseShippingCanceled(0);
                        $this->_order->setBaseSubtotalCanceled(0);
                        $this->_order->setBaseTaxCanceled(0);
                        $this->_order->setBaseTotalCanceled(0);
                        $this->_order->setDiscountCanceled(0);
                        $this->_order->setShippingCanceled(0);
                        $this->_order->setSubtotalCanceled(0);
                        $this->_order->setTaxCanceled(0);
                        $this->_order->setTotalCanceled(0);

                        foreach ($items as $item) {
                            $item->setQtyCanceled(0);
                            $item->setTaxCanceled(0);
                            $item->setHiddenTaxCanceled(0);
                            $item->save();
                        }
                    }

                    //set order status
                    $this->_order->setState(Mage_Sales_Model_Order::STATE_PROCESSING);
                    $this->_order->setStatus($this->settings->get('order_status_approved'));

                    // Prepare payment object
                    $payment = $this->_order->getPayment();
                    $payment->setTransactionId($transaction_id);
                    $payment->setLastTransId($transaction_id);
                    $payment->setIsTransactionClosed(1);
                    $payment->setTransactionAdditionalInfo(Mage_Sales_Model_Order_Payment_Transaction::RAW_DETAILS, array('source' => $source_name, 'source_number' => $source_number));

                    // Save payment, transaction and order
                    $payment->save();

                    // Create invoice if not exists
                    if (!$this->_order->hasInvoices()) {
                        $invoice = $this->_order->prepareInvoice()
                            ->register()
                            ->capture()
                            ->addComment($message, 1, 1)
                            ->save();
    
                        $this->_order->addRelatedObject($invoice);
                    }

                    // Send notifications to the user
                    $this->_order->sendNewOrderEmail();
                    $this->_order->setEmailSent(true);

                } else if ($statusName === 'refunded') {
                    // Cancel Sale
                    $this->_order->cancel()->setStatus($this->settings->get('order_status_refunded'));
                } else if($statusName === 'authorized') {
                    //set order status
                    $this->_order->setStatus('authorized_mobbex');
                } else {
                    $this->_order->cancel()->setState($this->settings->get('order_status_cancelled'), true, $message);
                }

                $this->logger->log('debug', 'Payment Controller > notificationAction | Save Order: ' . $this->_order->getId());

                // Save the order
                $this->_order->save();

                $this->_checkoutSession->unsQuoteId();
            }
        } catch (Exception $e) {
            $this->logger->log('Error', 'Payment Controller > notificationAction | Exception: ', $e->getMessage());
        }
    }

    public function captureAction()
    {
        try {
            // Get order id and the token
            $id    = $this->getRequest()->getParam('order_id');
            $token = urldecode($this->getRequest()->getParam('mbbxToken', ''));
            $this->_order->loadByIncrementId($id);

            if (!\Mobbex\Repository::validateToken($token))
                throw new \Exception('Invalid Token on capture action', 1);

            // Get transaction data from db
            $transaction = $this->mobbexTransaction->getMobbexTransaction(['order_id' => $id, 'parent' => 1]);

            // Make capture request
            $result = \Mobbex\Api::request([
                'method' => 'POST',
                'uri'    => "operations/$transaction[payment_id]/capture",
                'body'   => ['total' => $this->_order->getGrandTotal()],
            ]);

            if (!$result)
                throw new \Exception('Uncaught Exception on Mobbex Request', 500);
        } catch (\Exception $e) {
            // Add message to admin panel and debug
            $this->logger->log('error', $e->getMessage(), isset($e->data) ? $e->data : []);
        }

        return Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/sales_order/view", array('order_id' => $this->_order->getId())));
    }

    // The cancel action is triggered when an order is to be cancelled
    public function cancelAction()
    {
        if ($this->_checkoutSession->getLastRealOrderId()) {
            $this->_order->loadByIncrementId($this->_checkoutSession->getLastRealOrderId());
            if ($this->_order->getId()) {
                // Flag the order as 'cancelled' and save it
                $this->_order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, 'Gateway has declined the payment.')->save();
            }
        }

        $this->logger->log('debug', 'Payment Controller > cancelAction | Order Cancelled', ['order_id' => $this->_order->getId()]);

        Mage_Core_Controller_Varien_Action::_redirect('checkout/onepage/failure', array('_secure' => true));
    }

    /** Use to get checkout data via ajax */
    public function getCheckoutAction()
    {
        try {
            // Retrieve order
            $orderId = $this->_checkoutSession->getLastRealOrderId();
            $this->_order->loadByIncrementId($orderId);

            // Get Checkout Data
            $checkout = $this->helper->createCheckout($orderId);

            $mobbex_data['returnUrl']  = $this->helper->getModuleUrl('response', ['orderId' => $orderId]);
            $mobbex_data['checkoutId'] = isset($checkout['id']) ? $checkout['id'] : '';
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
            
        } catch (\Exception $e) {
            $this->logger->log('error', $e->getMessage(), isset($e->data) ? $e->data : []);
            return false;
        }

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
        if ($statusCode == 2 || $statusCode == 100 || $statusCode == 201)
            $name = 'inProcess';
        else if($statusCode == 3)
            $name = 'authorized';
        else if ($statusCode == 4 || $statusCode >= 200 && $statusCode < 400)
            $name = 'approved';
        else
            $name = $order->getStatus() != 'pending' ? 'refunded' : 'cancelled';

        return $name;
    }
}
