<?php
class Mobbex_Mobbex_Helper_Data extends Mage_Core_Helper_Abstract
{
    const VERSION = '2.0.1';

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

    public function createCheckout($order)
    {
		// Init Curl
		$curl = curl_init();
		
        // Create an unique id
		$tracking_ref = $this->getReference($order);

		$products = $items = array();
		
        foreach($order->getAllItems() as $item) {

			$subscription = Mage::helper('mobbex/settings')->getProductSubscription($item->getProductId());
			$entity       = Mage::helper('mobbex/settings')->getProductEntity($item);

			if($subscription['enable'] === 'yes'){
				$items[] = [
					'type'      => 'subscription',
					'reference' => $subscription['uid']
				];
			} else {
				$items[] = array(
					"image"       => (string)Mage::helper('catalog/image')->init($item->getProduct(), 'image')->resize(150), 
					"description" => $item->getName(), 
					"quantity"    => $item->getQtyOrdered(), 
					"total"       => round($item->getPrice(),2),
					"entity"      => $entity,
				);
			}

			$products[] = $item->getProduct();
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
			'uid'   => $order->getCustomerId(),
			'phone' => !empty($order->getBillingAddress()) ? $order->getBillingAddress()->getTelephone() : null,
			'identification' => Mage::getModel('mobbex/customfield')->getCustomField($order->getCustomerId(), 'customer', 'dni'),
		];

		$return_url = $this->getModuleUrl('response', $queryParams);

        // Create data
        $data = $this->executeHook('mobbexCheckoutRequest', true, [
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
			'multivendor'  => Mage::getStoreConfig('payment/mobbex/multivendor'),
			'merchants'    => Mage::helper('mobbex/settings')->getMerchants($items),
			"wallet"       => ((bool) Mage::getStoreConfig('payment/mobbex/wallet') && Mage::getSingleton('customer/session')->isLoggedIn()),
			'addresses'    => $this->getAddresses([$order->getBillingAddress()->getData(), $order->getShippingAddress()->getData()]),
			'options'	   => [
				'embed'    => (Mage::getStoreConfig('payment/mobbex/embed') == true),
				'domain'   => str_replace('www.', '', parse_url(Mage::getBaseUrl(), PHP_URL_HOST)),
				'platform' => $this->getPlatform(),
                'theme'    => $this->getTheme(),
				'redirect' => [
                    'success' => true,
                    'failure' => false,
                ],
			],
		]);

		//debug data
		$this->debug('Checkout data:', $data);

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
            $this->debug("cURL Error #:" . $err, '', true);
        } else {
			
			$res = json_decode($response, true);
			
			if(!empty($res['data'])) {
				$res['data']['return_url'] = $return_url;
				return $res['data'];
				
			} else {
				$this->debug("Failed getting checkout response data is empty", $res, true);
				
				// Restore Order
				if(Mage::getSingleton('checkout/session')->getLastRealOrderId()){

					if ($lastQuoteId = Mage::getSingleton('checkout/session')->getLastQuoteId()){
						$quote = Mage::getModel('sales/quote')->load($lastQuoteId);
						$quote->setIsActive(true)->save();
					}

					// Send error message
					Mage::getSingleton('core/session')->addError(Mage::helper('mobbex')->__('The payment has failed.'));
				}
				return false;
			}
        }
	}

	/**
     * Create checkout when wallet is active,
     *  using a quote instead of an order.
     *  can't use an order object beacouse there is a duplication problem
     * @return bool
     */
    public function createCheckoutFromQuote($quoteData)
    {
        $curl = curl_init();

        // set quote description as #QUOTEID
        $description = __('Quote #').$quoteData['entity_id'] ;

        // get order amount
        $orderAmount = round($quoteData['price'], 2);

        // get customer data
        $customer = [
            'email' => $quoteData['email'], 
            'name' => $quoteData['customer']['firstname'],
            //Customer id added for wallet usage
            'uid' => $quoteData['customer_id'],
            'identification' => Mage::getModel('mobbex/customfield')->getCustomField($quoteData['entity_id'], 'customer', 'dni'),
        ];
        if ($quoteData['customer']){
            if ($quoteData['customer']['telephone']) {
                $customer['phone'] = $quoteData['customer']['telephone'];
            }
        }
        //get quote to retrieve shipping amount
        $quote_grand_total = $quoteData['quote']->getGrandTotal();
        
        $items = [];

        foreach($quoteData['items'] as $product) {
			
			$prd = Mage::helper('catalog/product')->getProduct($product->getId(), null, null);
			$subscription = Mage::helper('mobbex/settings')->getProductSubscription($product->getProductId());

			if($subscription['enable'] === 'yes'){
				$items[] = [
					'type'      => 'subscription',
					'reference' => $subscription['uid']
				];
			} else {
				$items[] = array(
					"image" => (string)Mage::helper('catalog/image')->init($prd, 'image')->resize(150), 
					"description" => $product->getName(), 
					"quantity" => $product->getQtyOrdered(), 
					"total" => round($product->getPrice(),2) 
				);
			}
		}
        
        if ($quoteData['shipping_total'] > 0) {
            $items[] = [
                'description' => 'Shipping Amount',
                'total'       => $quoteData['shipping_total'],
            ];
        }elseif($quote_grand_total > $orderAmount){
            $shipping_amount = $quote_grand_total - $orderAmount;
            $items[] = [
                'description' => 'Shipping Amount',
                'total'       => ($shipping_amount),
            ];
            $orderAmount = $orderAmount + $shipping_amount;
        }
        
        // Return Query Params
		$queryParams = array('orderId' => $quoteData['entity_id']);

        // Create data
        $data = [
            'reference'    => 'mag_order_'.$quoteData['entity_id'],
            'currency'     => 'ARS',
            'description'  => $description,
            'test'         => (Mage::getStoreConfig('payment/mobbex/test_mode') == true),
            'return_url'   => '',
            'webhook'      => '',
            'items'        => $items,
            'total'        => (float) $orderAmount,
            'customer'     => $customer,
            'timeout'      => 5,
            'installments' => $this->getInstallments($quoteData['items']),
            "multicard"    => (Mage::getStoreConfig('payment/mobbex/multicard') == true),
			"wallet"       => ((bool) Mage::getStoreConfig('payment/mobbex/wallet') && Mage::getSingleton('customer/session')->isLoggedIn()),
			'addresses'    => $quoteData['addresses'],
            "options"      => [
				'embed'    => (Mage::getStoreConfig('payment/mobbex/embed') == true),
				'domain'   => str_replace('www.', '', parse_url(Mage::getBaseUrl(), PHP_URL_HOST)),
				'platform' => $this->getPlatform(),
                'theme'    => [
					'type'   => 'light', 
					'colors' => null
				],
                "redirect"   => [
                    "success"  => true,
                    "failure"  => false,
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
            CURLOPT_HTTPHEADER => $this->getHeaders(),
        ]);
        
        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return false;
        } else {
            $res = json_decode($response, true);
            return $res['data'];
        }

    }

	/**
	 * Get Addresses data for Mobebx Checkout.
	 * 
	 * @param array $addressesData
	 * 
	 * @return array $addresses
	 */
	public function getAddresses($addressesData)
	{
		$addresses = [];

		foreach ($addressesData as $address) {
			$region = Mage::getModel('directory/region')->load($address['region_id'])->getData();
            $street = trim(!empty($address['street']) ? $address['street'] : '');

			$addresses[] = [
				'type'         => isset($address["address_type"]) ? $address["address_type"] : '',
				'country'      => isset($address["country_id"]) ? $this->convertCountryCode($address["country_id"]) : '',
				'street'       => trim(preg_replace('/(\D{0})+(\d*)+$/', '', $street)),
				'streetNumber' => str_replace(preg_replace('/(\D{0})+(\d*)+$/', '', $street), '', $street),
				'streetNotes'  => '',
				'zipCode'      => isset($address["postcode"]) ? $address["postcode"] : '',
				'city'         => isset($address["city"]) ? $address["city"] : '',
				'state'        => (isset($address["country_id"]) && isset($region['code'])) ? str_replace((string) $address["country_id"] . '-', '', (string) $region['code']) : ''
			];
		}

		return $addresses;
	}

	/**
	 * Converts the WooCommerce country codes to 3-letter ISO codes.
	 * 
	 * @param string $code 2-Letter ISO code.
	 * 
	 * @return string|null
	 */
	public function convertCountryCode($code)
	{
		$countries = include ('iso-3166/country-codes.php') ?: [];

		return isset($countries[$code]) ? $countries[$code] : null;
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
			$this->debug('Curl Error #:', $err, true);
			Mage::throwException('Curl Error #:' . $err);
		} else {
			$res = json_decode($response, true);

			if(!isset($res['data']) || !$res || empty($res['data'])){
				$this->debug("Failed getting sources response data is empty", $res, true);
				return;
			}

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
     * 
     * @return array
     */
    public function getInstallments($items)
    {
        $installments = $inactivePlans = $activePlans = [];

        // Get plans from order products
        foreach ($items as $item) {
            $inactivePlans = array_merge($inactivePlans, $this->getInactivePlans($item->getId()));
            $activePlans   = array_merge($activePlans, $this->getActivePlans($item->getId()));
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
		
		// Save data
		$this->_config = new Mage_Core_Model_Config();
		$this->_config->saveConfig('payment/mobbex/entity_data', json_encode($res['data']));

		return $res['data'];
	}

	/**
     * Retrieve active common plans from a product and its categories.
     * 
     * @param int $productId
     * 
     * @return array
     */
    public function getInactivePlans($productId)
    {
        $product       = Mage::getModel('catalog/product')->load($productId);
		$inactivePlans = json_decode($this->fields->getCustomField($productId, 'product', 'common_plans'), true) ?: [];

        foreach ($product->getCategoryIds() as $categoryId)
            $inactivePlans = array_merge($inactivePlans, json_decode($this->fields->getCustomField($categoryId, 'category', 'common_plans'), true) ?: []);

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
        $activePlans = json_decode($this->fields->getCustomField($productId, 'product', 'advanced_plans'), true) ?: [];
	
        foreach ($product->getCategoryIds() as $categoryId)
            $activePlans = array_merge($activePlans, json_decode($this->fields->getCustomField($categoryId, 'category', 'advanced_plans'), true) ?: []);

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

	/**
     * @return array
     */
    private function getTheme()
    {
        return [
            "type" => Mage::getStoreConfig('payment/mobbex/theme'),
            "background" => Mage::getStoreConfig('payment/mobbex/background_color'),
            "colors" => [
                "primary" => Mage::getStoreConfig('payment/mobbex/primary_color'),
            ],
        ];
    }

	// DEBUG MODE //
	/**
	 * Send Mobbex errors and other useful data to magento log system if debug mode is active.
	 * 
	 * @param string $message
	 * @param mixed $data
	 * @param bool $force
	 * @param bool $die
	 */
	public function debug($message = 'debug', $data = null, $force = false, $die = false)
	{
		if((Mage::getStoreConfig('payment/mobbex/debug_mode') == false) && !$force)
			return;

		Mage::log(
			"Mobbex: $message " . (is_string($data) ? $data : json_encode($data)),
			null,
			'mobbex_debug_'.date('m_Y').'.log',
			true
		);

		if($die)
			die($message);
	}

	/**
     * Execute a hook and retrieve the response.
     * 
     * @param string $name The hook name (in camel case).
     * @param bool $filter Filter first arg in each execution.
     * @param mixed ...$args Arguments to pass.
     * 
     * @return mixed Last execution response or value filtered. Null on exceptions.
     */
    public function executeHook($name, $filter = false, ...$args)
    {
        try {
            // Use snake case to search event
            $eventName = ltrim(strtolower(preg_replace('/[A-Z]([A-Z](?![a-z]))*/', '_$0', $name)), '_');

            // Get registered observers and first arg to return as default
			$eventConfig = Mage::app()->getConfig()->getEventConfig('global', $eventName);
            $value       = $filter ? reset($args) : false;
			$observers   = $eventConfig->observers ? $eventConfig->observers->children() : [];

			foreach ($observers as $observerData) {

				// Instance observer
				$instanceMethod = 'get'.$observerData->type;
				$observer       = Mage::{$instanceMethod}((string) $observerData->class);

				// Get method to execute
				$method = [$observer, (string) $observerData->method];

				// Only execute if is callable
				if (!is_callable($method))
					continue;
	
				$value = call_user_func_array($method, $args);

				if ($filter)
					$args[0] = $value;

			}

            return $value;
        } catch (\Exception $e) {
            $this->debug('Mobbex Hook Error: ', $e->getMessage(), true);
        }
    }
} 

