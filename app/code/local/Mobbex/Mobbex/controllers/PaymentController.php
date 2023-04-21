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
            $this->logger->debug('debug', 'Payment Controller > responseAction | Params: ', $this->getRequest()->getParams());
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
                    $this->logger->debug('error', 'The payment has failed');

                    //Redirect to cart
                    $this->_redirect('checkout/cart', array('_secure' => true));
                }
            }

        } catch (\Exception $e) {
            $this->logger->debug('error', $e->getMessage);
        }

    }

    public function notificationAction()
    {
        try {
            // Get Data
            $postData = isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] == 'application/json' ? json_decode(file_get_contents('php://input'), true) : $this->getRequest()->getPost();
            $orderId  = $this->getRequest()->getParam('orderId');
            $token    = $this->getRequest()->getParam('mbbxToken');

            if (!\Mobbex\Repository::validateToken($token))
                throw new \Exception("Invalid Token: $token", 1);

            // Load the Order
            $this->_order->loadByIncrementId($orderId);

            $res = $this->formatWebhookData($postData['data'], $orderId, (!!$this->settings->get('multicard')), !!$this->settings->get('multivendor'));
            
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
            if ($res['parent'] == false) {
                return;
            }

            //Debug the response data
            $this->logger->debug("debug", "Payment Controller > notificationAction | Processing Webhook Data: ", compact('orderId', 'res'));

            if (isset($orderId) && !empty($status)) {

                
                // Get Payment Sources
                $source_name = $res['source_name'];
                $source_number = 'N/A';
                if (!empty($res['source_number']))
                    $source_number = ' ' . $res['source_number'];

                $user_name  = $res['user']['name'];
                $user_email = $res['user']['email'];

                $this->logger->debug("debug", "Payment Controller > notificationAction | Saving state for order: " . $this->_order->getId());

                $paymentComment = 'Método de pago: ' . $source_name . '. Número: ' . $source_number;
                $userComment = 'Pago realizado por: ' . $user_name . ' - ' . $user_email;

                $this->_order->addStatusHistoryComment($paymentComment);
                $this->_order->addStatusHistoryComment($userComment);

                $statusName = $this->getStatusName($this->_order, $status);

                // Get Order status
                if ($statusName == 'inProcess') {
                    $this->_order->setStatus($this->settings->get('order_status_in_process'));
                } else if ($statusName === 'Approved') {

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

                    // Send notifications to the user
                    $this->_order->sendNewOrderEmail();
                    $this->_order->setEmailSent(true);

                } else if ($statusName === 'Refunded') {
                    // Cancel Sale
                    $this->_order->cancel()->setStatus($this->settings->get('order_status_refunded'));
                } else {
                    $this->_order->cancel()->setState($this->settings->get('order_status_cancelled'), true, $message);
                }

                $this->logger->debug('debug', 'Payment Controller > notificationAction | Save Order: ' . $this->_order->getId());

                // Save the order
                $this->_order->save();

                $this->_checkoutSession->unsQuoteId();
            }
        } catch (Exception $e) {
            $this->logger->debug('Error', 'Payment Controller > notificationAction | Exception: ', $e->getMessage());
        }
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

        $this->logger->debug('debug', 'Payment Controller > cancelAction | Order Cancelled', ['order_id' => $this->_order->getId()]);

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
            
        } catch (\Exception $e) {
            $this->logger->debug('error', $e->getMessage(), isset($e->data) ? $e->data : []);
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
        if ($statusCode == 2 || $statusCode == 3 || $statusCode == 100 || $statusCode == 201) {
            $name = 'InProcess';
        } else if ($statusCode == 4 || $statusCode >= 200 && $statusCode < 400) {
            $name = 'Approved';
        } else {
            $name = $order->getStatus() != 'pending' ? 'Cancelled' : 'Refunded';
        }

        return $name;
    }

    /**
     * Format the webhook data in an array.
     * 
     * @param array $webhook_data
     * @param int $order_id
     * @param bool $multicard
     * @param bool $multivendor
     * @return array $data
     * 
     */
    public function formatWebhookData($webhookData, $orderId, $multicard, $multivendor)
    {
        $data = [
            'order_id'           => $orderId,
            'parent'             => $this->isParent($webhookData['payment']['operation']['type'], $multicard, $multivendor) ? true : false,
            'operation_type'     => isset($webhookData['payment']['operation']['type']) ? $webhookData['payment']['operation']['type'] : '',
            'payment_id'         => isset($webhookData['payment']['id']) ? $webhookData['payment']['id'] : '',
            'description'        => isset($webhookData['payment']['description']) ? $webhookData['payment']['description'] : '',
            'status_code'        => isset($webhookData['payment']['status']['code']) ? $webhookData['payment']['status']['code'] : '',
            'status_message'     => isset($webhookData['payment']['status']['message']) ? $webhookData['payment']['status']['message'] : '',
            'source_name'        => isset($webhookData['payment']['source']['name']) ? $webhookData['payment']['source']['name'] : 'Mobbex',
            'source_type'        => isset($webhookData['payment']['source']['type']) ? $webhookData['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($webhookData['payment']['source']['reference']) ? $webhookData['payment']['source']['reference'] : '',
            'source_number'      => isset($webhookData['payment']['source']['number']) ? $webhookData['payment']['source']['number'] : '',
            'source_expiration'  => isset($webhookData['payment']['source']['expiration']) ? json_encode($webhookData['payment']['source']['expiration']) : '',
            'source_installment' => isset($webhookData['payment']['source']['installment']) ? json_encode($webhookData['payment']['source']['installment']) : '',
            'installment_name'   => isset($webhookData['payment']['source']['installment']['description']) ? json_encode($webhookData['payment']['source']['installment']['description']) : '',
            'installment_amount' => isset($webhookData['payment']['source']['installment']['amount']) ? $webhookData['payment']['source']['installment']['amount'] : '',
            'installment_count'  => isset($webhookData['payment']['source']['installment']['count']) ? $webhookData['payment']['source']['installment']['count'] : '',
            'source_url'         => isset($webhookData['payment']['source']['url']) ? json_encode($webhookData['payment']['source']['url']) : '',
            'cardholder'         => isset($webhookData['payment']['source']['cardholder']) ? json_encode(($webhookData['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($webhookData['entity']['name']) ? $webhookData['entity']['name'] : '',
            'entity_uid'         => isset($webhookData['entity']['uid']) ? $webhookData['entity']['uid'] : '',
            'customer'           => isset($webhookData['customer']) ? json_encode($webhookData['customer']) : '',
            'checkout_uid'       => isset($webhookData['checkout']['uid']) ? $webhookData['checkout']['uid'] : '',
            'total'              => isset($webhookData['payment']['total']) ? $webhookData['payment']['total'] : '',
            'currency'           => isset($webhookData['checkout']['currency']) ? $webhookData['checkout']['currency'] : '',
            'risk_analysis'      => isset($webhookData['payment']['riskAnalysis']['level']) ? $webhookData['payment']['riskAnalysis']['level'] : '',
            'data'               => json_encode($webhookData),
            'created'            => isset($webhookData['payment']['created']) ? $webhookData['payment']['created'] : '',
            'updated'            => isset($webhookData['payment']['updated']) ? $webhookData['payment']['created'] : '',
            'user'               => [
                'name' => isset($webhookData['user']['name']) ? $webhookData['user']['name'] : '',
                'email' => isset($webhookData['user']['email']) ? $webhookData['user']['email'] : '',
            ],

        ];

        return $data;
    }

    /**
     * Receives the webhook "opartion type" and return true if the webhook is parent and false if not
     * 
     * @param string $operationType
     * @param bool $multicard
     * @param bool $multivendor
     * @return bool true|false
     * @return bool true|false
     * 
     */
    public function isParent($operationType, $multicard, $multivendor)
    {
        if ($operationType === "payment.v2") {
            if ($multicard || $multivendor)
                return false;
        }

        return true;
    }
}
