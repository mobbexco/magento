<?php

class Mobbex_Mobbex_PaymentController extends Mage_Core_Controller_Front_Action
{
    // The redirect action is triggered when someone places an order
    public function redirectAction()
    {
        $embed = Mage::getStoreConfig('payment/mobbex/embed');
        if (!$embed) {
            $this->loadLayout();
    
            $block = $this->getLayout()->createBlock('Mage_Core_Block_Template', 'mobbex', array('template' => 'mobbex/redirect.phtml'));
            $this->getLayout()->getBlock('content')->append($block);
    
            $this->renderLayout();
        }
    }

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
        if ($status == 200 || $status == 2) {
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
        if ($this->getRequest()->isPost()) {
            // Get Data
            $insMessage = $this->getRequest()->getPost();
            $orderId = $this->getRequest()->getParam('orderId');

            // Load the Order
            $order = Mage::getModel('sales/order');
            $order->loadByIncrementId($orderId);

            $res = $insMessage['data'];

            // Get the Reference ( Transaction ID )
            $transaction_id = $res['payment']['id'];

            // Get the Status
            $status = $res['payment']['status']['code'];

            $message = $res['payment']['status']['message'] . ' ( Transacción: ' . $transaction_id . ' )';

            Mage::log('ORDER ID: ' . $orderId, null, 'mobbex_notification.log', true);
            Mage::log($res, null, 'mobbex_notification.log', true);

            // Payment was successful, so update the order's state, send order email and move to the success page
            if ($status == 200 || $status == 2) {
                try {
                    $source_type = $res['payment']['source']['type'];
					$source_name = $res['payment']['source']['name'];

                    // Get Source number in case of cards
                    $source_number = 'N/A';
                    if (isset($res['payment']['source']['number'])) {
                        $source_number = ' ' . $res['payment']['source']['number'];
					}
					
					$user_name = isset($res['user']['name']) ? $res['user']['name'] : '';
					$user_email = isset($res['user']['email']) ? $res['user']['email'] : '';

                    Mage::log('Saving state for Order: ' . $order->getId(), null, 'mobbex_notification.log', true);

                    // Get Order status
                    if ($status == 2) {
                        $order->setState(Mage_Sales_Model_Order::STATE_PENDING_PAYMENT, true, 'El cliente eligió un medio de pago en Efectivo. Se aguarda recepción de pago. Mensaje: ' . $message);
                    } else {
                        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $message);
					}

					$paymentComment = 'Método de pago: ' . $source_name . '. Número: ' . $source_number;
					$userComment = 'Pago realizado por: ' . $user_name . ' - ' . $user_email;
					
					$order->addStatusHistoryComment($paymentComment);
					$order->addStatusHistoryComment($userComment);

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

                    // Send notifications to the user
                    $order->sendNewOrderEmail();
                    $order->setEmailSent(true);

                    Mage::log('Save Order: ' . $order->getId(), null, 'mobbex_notification.log', true);

                    // Save the order
                    $order->save();

                    Mage::getSingleton('checkout/session')->unsQuoteId();
                } catch (Exception $e) {
					$this->messageManager->addExceptionMessage($e, $e->getMessage());
                }
            } else {
                // Cancel Sale
                $order->cancel()->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $message)->save();
            }
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

        $mobbex_data['returnUrl'] = $checkout['return_url'];
        $mobbex_data['checkoutId'] = $checkout['id'];
        $mobbex_data['orderId'] = $orderId;

        // Return data in json
        $this->getResponse()->clearHeaders()->setHeader(
            'Content-type',
            'application/json'
        );
        $this->getResponse()->setBody(
            Mage::helper('core')->jsonEncode($mobbex_data)
        );
    }
}
