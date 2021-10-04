<?php
class Mobbex_Mobbex_Helper_Data extends Mage_Core_Helper_Abstract
{
    const VERSION = '1.4.1';

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

	public function getHeaders() {
		$apiKey = Mage::getStoreConfig('payment/mobbex/api_key');
		$accessToken = Mage::getStoreConfig('payment/mobbex/access_token');

		return array(
            'cache-control: no-cache',
            'content-type: application/json',
            'x-api-key: ' . $apiKey,
            'x-access-token: ' . $accessToken,
        );
	}

	public function getModuleUrl($action, $queryParams) {
		return Mage::getUrl('mobbex/payment/' . $action, array('_secure' => true, '_query' => $queryParams)); 
	}

	public function getReference($order)
    {
        return 'mag_order_'.$order->getIncrementId().'_time_'.time();
	}

	private function getPlatform()
    {
        return [
            "name" => "magento_1",
            "version" => $this::VERSION,
            "platform_version" => Mage::getVersion(),
        ];
    }

	public function getInstallments($products)
	{
		$installments = $allCommonPlans = $allAdvancedPlans = [];

		foreach (self::$ahora as $ahoraPlan) {
			// Get 'ahora' plans from categories
			foreach ($this->getAllCategories($products) as $catId) {
				// If category has plan selected
				if ($this->fields->getCustomField($catId, 'category', $ahoraPlan) == 'yes') {
					// Add to installments
					$installments[] = '-' . $ahoraPlan;
					continue 2;
				}
			}

			// Get 'ahora' plans from products
			foreach ($products as $product) {
				// If product has plan selected
				if ($this->fields->getCustomField($product->getProductId(), 'product', $ahoraPlan) == 'yes') {
					// Add to installments
					$installments[] = '-' . $ahoraPlan;
					continue 2;
				}
			}
		}

		foreach ($products as $product) {
			$productId = $product->getProductId();

			// Get common and advanced plans from product
			$commonPlans   = $this->fields->getCustomField($productId, 'product', 'common_plans') ?: [];
			$advancedPlans = $this->fields->getCustomField($productId, 'product', 'advanced_plans') ?: [];

			$allCatCommonPlans = $allCatAdvancedPlans = [];

			// Get common and advanced plans from product categories
			foreach ($product->getProduct()->getCategoryIds() as $catId) {
				$catCommonPlans   = $this->fields->getCustomField($catId, 'category', 'common_plans') ?: [];
				$catAdvancedPlans = $this->fields->getCustomField($catId, 'category', 'advanced_plans') ?: [];

				$allCatCommonPlans   = array_merge($allCatCommonPlans, $catCommonPlans);
				$allCatAdvancedPlans = array_merge($allCatAdvancedPlans, $catAdvancedPlans);
			}

			$allCommonPlans   = array_merge($allCommonPlans, $commonPlans, $allCatCommonPlans);
			$allAdvancedPlans = array_merge($allAdvancedPlans, array_unique(array_merge($advancedPlans, $allCatAdvancedPlans)));
		}

		// Add common plans to installments
		foreach ($allCommonPlans as $commonPlan) {
			$installments[] = '-' . $commonPlan;
		}

		// Get all the advanced plans with their number of reps
        foreach (array_count_values($allAdvancedPlans) as $plan => $reps) {
            // Only if the plan is active on all products, add to installments
            if ($reps == count($products))
                $installments[] = '+uid:' . $plan;
        }

        return array_unique($installments);
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
			'multicard' => (Mage::getStoreConfig('payment/mobbex/multicard') == true),
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

	/**
	 * Get sources with common and advanced filtered plans from mobbex.
	 * 
	 * @param null|int $total
	 * @param null|array $inactivePlans
	 * @param null|array $activePlans
	 * 
	 * @return array
	 */
	public function getSources($total = null, $inactivePlans = null, $activePlans = null)
	{
		$curl = curl_init();

		$data = $total ? '?total=' . $total : null;

		$data .= self::getInstallmentsQuery($inactivePlans, $activePlans);

		curl_setopt_array($curl, [
			CURLOPT_URL 		   => "https://api.mobbex.com/p/sources$data",
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
     * Returns a query param with the installments of the product.
     * @param array $inactivePlans
     * @param array $activePlans
     */
    public static function getInstallmentsQuery($inactivePlans = null, $activePlans = null ) {
        
        $installments = [];
        
        //get plans
        if($inactivePlans) {
            foreach ($inactivePlans as $plan) {
                $installments[] = "-$plan";
            }
        }

        if($activePlans) {
            foreach ($activePlans as $plan) {
                $installments[] = "+uid:$plan";
            } 
        }

        //Build query param
        $query = http_build_query(['installments' => $installments]);
        $query = preg_replace('/%5B[0-9]+%5D/simU', '%5B%5D', $query);
        
        return $query;
    }   
}
