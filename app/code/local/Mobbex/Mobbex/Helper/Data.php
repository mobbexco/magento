<?php
class Mobbex_Mobbex_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function getHeaders() {
		$apiKey = Mage::getStoreConfig('payment/mobbex/api_key');
		$accessToken = Mage::getStoreConfig('payment/mobbex/access_token');

		return array(
            'cache-control: no-cache',
            'content-type: application/json',
            'x-access-token: ' . $accessToken,
            'x-api-key: ' . $apiKey
        );
	}

	public function getModuleUrl($action, $queryParams) {
		return Mage::getUrl('mobbex/payment/' . $action, array('_secure' => true, '_query' => $queryParams)); 
	}

	public function getReference($order)
    {
        return 'mag_oder_'.$order->getIncrementId().'_seed_'.mt_rand(100000, 999999);
	}
	
    public function createCheckout($order)
    {
		// Init Curl
		$curl = curl_init("https://api.mobbex.com/p/checkout");
		
        // Create an unique id
		$tracking_ref = $this->getReference($order);
		
		$items = array();
		$products = $order->getAllItems();
		
        foreach($products as $product) {
			$prd = Mage::helper('catalog/product')->getProduct($product->getId(), null, null);

            $items[] = array(
				"image" => (string)Mage::helper('catalog/image')->init($prd, 'image')->resize(150), 
				"description" => $product->getName(), 
				"quantity" => $product->getQtyOrdered(), 
				"total" => round($product->getPrice(),2) 
			);
		}

		// Add shipping item
		if (!empty($order->getShippingDescription())) {
            $items[] = [
                'description' => 'Envío: ' . $order->getShippingDescription(),
                'total' => $order->getShippingAmount(),
            ];
        }

		// Get Headers
		$headers = $this->getHeaders();

		// Return Query Params
		$queryParams = array('orderId' => $order->getIncrementId());

		$customer = [
			'name' => $order->getCustomerName(),
			'email' => $order->getCustomerEmail(),
			'phone' => !empty($order->getBillingAddress()->getTelephone()) ? $order->getBillingAddress()->getTelephone() : null,
		];
		
        // Create data
        $data = array(
            'reference' => $tracking_ref,
            'currency' => 'ARS',
            'description' => 'Orden #' . $order->getIncrementId(),
            'return_url' => $this->getModuleUrl('response', $queryParams),
            'items' => $items,
            'webhook' => $this->getModuleUrl('notification', $queryParams),
            'redirect' => 0,
			'total' => round($order->getGrandTotal(), 2),
			'options' => [
                'theme' => [
                    'type' => 'light', 
					'colors' => null
				],
			],
			'customer' => $customer,
		);

		$curl_data = array(
			CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_HTTPHEADER => $headers,
			CURLINFO_HEADER_OUT => $headers,
			CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
		);

		// Uncomment for Debugging
		// echo '<pre>' . print_r($curl_data, true) . '</pre>';

		// Set Curl Data
		curl_setopt_array($curl, $curl_data);
		
        $response = curl_exec($curl);
		$err = curl_error($curl);
		
		// Close Curl
		curl_close($curl);
		
        if ($err) {
            d("cURL Error #:" . $err);
        } else {
			$res = json_decode($response, true);
			
			if($res['data']) {
				return $res['data']['url'];
			} else {
				// Mage::app()->getFrontController()->getResponse()->setRedirect(Mage::getUrl('mobbex/payment/cancel', array('_secure' => true)));

				// Restore Order
				if(Mage::getSingleton('checkout/session')->getLastRealOrderId()){
					if ($lastQuoteId = Mage::getSingleton('checkout/session')->getLastQuoteId()){
						$quote = Mage::getModel('sales/quote')->load($lastQuoteId);
						$quote->setIsActive(true)->save();
					}

					// Send error message
					Mage::getSingleton('core/session')->addError(Mage::helper('mobbex')->__('The payment has failed.'));

					 //Redirect to cart
					$this->_redirect('checkout/cart');
				}
			}
        }
    }
}
