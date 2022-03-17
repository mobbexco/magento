<?php
class Mobbex_Mobbex_Helper_Data extends Mage_Core_Helper_Abstract
{
    const VERSION = '1.4.3';

	/**
	* All 'ahora' plan keys.
	*/
	public static $ahora = ['ahora_3', 'ahora_6', 'ahora_12', 'ahora_18'];
	
	/** @var Mobbex_Mobbex_Model_Customfield */
	public $fields;

	public function __construct()
	{
		$this->fields = Mage::getModel('mobbex/customfield');
	}

	//** CHECKOUT **/

    public function createCheckout($order)
    {
		// Init Curl
		$curl = curl_init();
		
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
			'phone' => !empty($order->getBillingAddress()) ? $order->getBillingAddress()->getTelephone() : null,
			'identification' => Mage::getModel('mobbex/customfield')->getCustomField($order->getCustomerId(), 'customer', 'dni'),
		];

		$return_url = $this->getModuleUrl('response', $queryParams);

        // Create data
        $data = [
            'reference'	   => $tracking_ref,
            'currency'	   => 'ARS',
            'description'  => 'Orden #' . $order->getIncrementId(),
			'test'		   => (Mage::getStoreConfig('payment/mobbex/test_mode') == true),
            'return_url'   => $return_url,
            'webhook'	   => $this->getModuleUrl('notification', $queryParams),
            'items'		   => $items,
			'total' 	   => round($order->getGrandTotal(), 2),
			'customer' 	   => $customer,
			'timeout' 	   => 5,
			'installments' => $this->getInstallments($products),
			'multicard'    => (Mage::getStoreConfig('payment/mobbex/multicard') == true),
			'options'	   => [
				'embed'    => (Mage::getStoreConfig('payment/mobbex/embed') == true),
				'domain'   => str_replace(['https://', 'http://'], '', rtrim(Mage::getBaseUrl(), '/')),
				'platform' => $this->getPlatform(),
                'theme'    => [
					'type'   => 'light', 
					'colors' => null
				],
				'redirect' => [
                    'success' => true,
                    'failure' => false,
                ],
			],
		];

		curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.mobbex.com/p/checkout",
            CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => "POST",
			CURLOPT_POSTFIELDS => json_encode($data),
			CURLOPT_HTTPHEADER => $headers
		]);
		
        $response = curl_exec($curl);
		$err = curl_error($curl);
		
		curl_close($curl);
		
        if ($err) {
            d("cURL Error #:" . $err);
        } else {
			$res = json_decode($response, true);
			
			if($res['data']) {
				$res['data']['return_url'] = $return_url;
				return $res['data'];
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

	/**
     * Return the Cuit/Tax_id using the ApiKey to request via web service
     * @return String Cuit
     */
	public function getCuit()
	{
        $curl = curl_init();
        $cuit = null;

        $headers = $this->getHeaders();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.mobbex.com/p/entity/validate",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            //search the cuit in the plugins config if cant get it from api request
			if($this->config){
				$cuit = $this->config->getCuit();
			}
        } else {
            $res = json_decode($response, true);
			if($res['data']){
				$cuit = $res['data']['tax_id'];
			}
        }
        return $cuit; 
    }

	public function getHeaders()
	{
		return [
            'cache-control: no-cache',
            'content-type: application/json',
            'x-api-key: ' . \Mage::getStoreConfig('payment/mobbex/api_key'),
            'x-access-token: ' . \Mage::getStoreConfig('payment/mobbex/access_token'),
            'x-ecommerce-agent: Magento/' . \Mage::getVersion() . ' Plugin/' . $this::VERSION,
		];
	}

	public function getModuleUrl($action, $queryParams) {
		return Mage::getUrl('mobbex/payment/' . $action, array('_secure' => true, '_query' => $queryParams)); 
	}

	public function getReference($order)
    {
        return 'mag_order_'.$order->getIncrementId();
	}

	private function getPlatform()
	{
		return [
			'name'	    => 'magento',
			'version'   => $this::VERSION,
			'ecommerce' => [
				'magento' => Mage::getVersion(),
			],
		];
	}

	/** SOURCES **/

	/**
	 * Get sources with common and advanced filtered plans from mobbex.
	 * 
	 * @param null|int $total
	 * @param null|array $inactivePlans
	 * @param null|array $activePlans
	 * 
	 * @return array
	 */
	public function getSources($total = null, $installments = [])
	{
		$entityData = $this->getEntityData();

        if (!$entityData)
            return [];

		

		$curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => "https://api.mobbex.com/p/sources/list/$entityData[countryReference]/$entityData[tax_id]" . ($total ? "?total=$total" : ''),
            CURLOPT_HTTPHEADER     => $this->getHeaders(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => json_encode(compact('installments')),
        ]);

        $response = curl_exec($curl);
        $error    = curl_error($curl);

        curl_close($curl);

        if ($error)
            Mage::log('Sources Obtaining cURL Error' . $error, "mobbex_error_" . date('m_Y') . ".log");

        $result = json_decode($response, true);

		if (empty($result['result']))
			Mage::log('Sources Obtaining Error', "mobbex_error_" . date('m_Y') . ".log");

		return isset($result['data']) ? $result['data'] : [];
	}

	/**
	 * Get sources with advanced rule plans from mobbex.
	 * 
	 * @param string $rule
	 * 
	 * @return array
	 */
	public function getSourcesAdvanced($rule = 'externalMatch')
	{
		$curl = curl_init();

		curl_setopt_array($curl, [
			CURLOPT_URL 		   => str_replace('{rule}', $rule, 'https://api.mobbex.com/p/sources/rules/{rule}/installments'),
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING 	   => "",
			CURLOPT_MAXREDIRS 	   => 10,
			CURLOPT_TIMEOUT 	   => 30,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => "GET",
			CURLOPT_HTTPHEADER	   => $this->getHeaders()
		]);

		$response = curl_exec($curl);
		$err = curl_error($curl);

		curl_close($curl);

		if ($err) {
			Mage::log('Curl Error #:' . $err);
			Mage::throwException('Curl Error #:' . $err);
		} else {
			$res = json_decode($response, true);

			if ($res['data']) {
				return $res['data'];
			}
		}

		return [];
	}

	/**
     * Retrieve installments checked on plans filter of each item.
     * 
     * @param array $items
     * @param bool $isQuote
     * 
     * @return array
     */
    public function getInstallments($items, $isQuote = false)
    {
        $installments = $inactivePlans = $activePlans = [];

        // Get plans from order products
        foreach ($items as $item) {
            $id = is_string($item) ? $item : ($isQuote ? $item['product_id'] : $item->getProductId());

            $inactivePlans = array_merge($inactivePlans, $this->getInactivePlans($id));
            $activePlans   = array_merge($activePlans, $this->getActivePlans($id));
        }

        // Add inactive (common) plans to installments
        foreach ($inactivePlans as $plan)
            $installments[] = '-' . $plan;

        // Add active (advanced) plans to installments only if the plan is active on all products
        foreach (array_count_values($activePlans) as $plan => $reps) {
            if ($reps == count($items))
                $installments[] = '+uid:' . $plan;
        }

        // Remove duplicated plans and return
        return array_values(array_unique($installments));
    }

	/**
     * Get entity data from Mobbex API or db if possible.
     * 
     * @return string[] 
     */
	public function getEntityData()
	{
		// First, try to get from db
    	$entityData = Mage::getStoreConfig('payment/mobbex/entity_data') ?: false;

    	if ($entityData)	
    	    return json_decode($entityData, true);
    	
		$curl = curl_init();

    	curl_setopt_array($curl, [	
			CURLOPT_URL            => "https://api.mobbex.com/p/entity/validate",
    	    CURLOPT_HTTPHEADER     => $this->getHeaders(),
    	    CURLOPT_RETURNTRANSFER => true,	
			CURLOPT_ENCODING       => "",
    	    CURLOPT_MAXREDIRS      => 10,
    	    CURLOPT_TIMEOUT        => 30,
    	    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    	    CURLOPT_CUSTOMREQUEST  => 'GET',
    	]);	

		$response = curl_exec($curl);
		$error    = curl_error($curl);	
    	curl_close($curl);	

		if ($error)	
			return Mage::log('Entity Data Obtaining cURL Error' . $error, "mobbex_error_" . date('m_Y') . ".log");	
		$res = json_decode($response, true); 	
		if (empty($res['data']))	
			return Mage::log('Entity Data Obtaining Error', "mobbex_error_" . date('m_Y') . ".log");	
		error_log('Log Message: ' . "\n" . json_encode($res, JSON_PRETTY_PRINT) . "\n", 3, 'log.log');
		
		// Save data
		$this->_config = new Mage_Core_Model_Config();
		$this->_config->saveConfig('payment/mobbex/entity_data', json_encode($res['data']));

		return $res['data'];
	}

	/**
     * Retrieve active advanced plans from a product and its categories.
     * 
     * @param int $productId
     * 
     * @return array
     */
    public function getInactivePlans($productId)
    {
        $product       = Mage::getModel('catalog/product')->load($productId);
		$inactivePlans = $this->fields->getCustomField($productId, 'product', 'common_plans') ?: [];

        foreach ($product->getCategoryIds() as $categoryId)
            $inactivePlans = array_merge($inactivePlans, $this->fields->getCustomField($categoryId, 'category', 'common_plans') ?: []);

        // Remove duplicated and return
        return array_unique($inactivePlans);
    }

    /**
     * Retrieve active advanced plans from a product and its categories.
     * 
     * @param int $productId
     * 
     * @return array
     */
    public function getActivePlans($productId)
    {
        $product     = Mage::getModel('catalog/product')->load($productId);
        $activePlans = $this->fields->getCustomField($productId, 'product', 'advanced_plans') ?: [];
	
        foreach ($product->getCategoryIds() as $categoryId)
            $activePlans = array_merge($activePlans, $this->fields->getCustomField($categoryId, 'category', 'advanced_plans') ?: []);

        // Remove duplicated and return
        return array_unique($activePlans);
    }

		/**
	 * Return categories ids from an array of products
	 * @param $listProducts : array
	 * @return array
	 */
	private function getAllCategories($listProducts)
	{
		
		$categories_id = array();
		foreach ($listProducts as $product) {
			//Search for the product object
			$productId = $product->getProductId();
			$prod = Mage::getModel('catalog/product')->load($productId);
			$categories = $prod->getCategoryIds();//array of cateries ids
			foreach ($categories as $cat_id) {
				if(!in_array($cat_id, $categories_id)){
					array_push($categories_id,$cat_id);
				}
			}
		}
		return $categories_id;
	}

}
