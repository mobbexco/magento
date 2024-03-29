<?php

/**
 * Do not rename this class. Magento uses it name to get module translations.
 */
class Mobbex_Mobbex_Helper_Data extends Mage_Core_Helper_Abstract
{
    const VERSION = '3.1.0';

    public function __construct()
	{
		//Set properties
		\Mage::helper('mobbex/instantiator')->setProperties($this, ['settings', 'logger', 'customField', '_order']);
	}

    public function createCheckout($orderId)
    {
		//Load Order
		$this->_order->loadByIncrementId($orderId);

		//Load customer Data
		$customer = [
			'name'           => $this->_order->getCustomerName(),
			'email'          => $this->_order->getCustomerEmail(),
			'uid'            => $this->_order->getCustomerId(),
			'phone'          => !empty($this->_order->getBillingAddress()) ? $this->_order->getBillingAddress()->getTelephone() : null,
			'identification' => $this->customField->getCustomField($this->_order->getCustomerId(), 'customer', 'dni'),
		];
		
		//Load items
		$products     = $items = array();
		$orderedItems = $this->_order->getAllItems();

        foreach($orderedItems as $item) {

			$product      = $item->getProduct();
			$products[]   = $product;
			$subscription = $this->settings->getProductSubscription($product->getId());

			if($subscription['enable'] === 'yes'){
				$items[] = [
					'type'      => 'subscription',
					'reference' => $subscription['uid'],
					'total'     => round($item->getPrice(),2)
				];
			} else {
				$items[] = array(
					"image"       => (string)Mage::helper('catalog/image')->init($item->getProduct(), 'image')->resize(150), 
					"description" => $item->getName(), 
					"quantity"    => $item->getQtyOrdered(), 
					"total"       => round($item->getPrice(),2),
					"entity"      => $this->settings->getProductEntity($product),
				);
			}
		}

		// Add shipping item
		if (!empty($this->_order->getShippingDescription())) {
            $items[] = [
                'description' => 'Envío: ' . $this->_order->getShippingDescription(),
                'total'       => $this->_order->getShippingAmount(),
            ];
        }

		// Get products active plans
		extract($this->settings->getProductPlans($products));

		// Return order id and token Query Params
		$queryParams = array(
			'orderId'   => $this->_order->getIncrementId(),
			'mbbxToken' => \Mobbex\Repository::generateToken()
		);
		
		//Create Mobbex Checkout
		$mobbexCheckout = new \Mobbex\Modules\Checkout(
			$orderId,
			round($this->_order->getGrandTotal(), 2),
			$this->getModuleUrl('response', $queryParams),
			$this->getModuleUrl('notification', $queryParams),
			$items,
			\Mobbex\Repository::getInstallments($orderedItems, $common_plans, $advanced_plans),
			$customer,
			array_filter([
				$this->getAddressData($this->_order->getBillingAddress()),
				$this->getAddressData($this->_order->getShippingAddress())
			]),
			'all',
			'mobbexCheckoutRequest'
		);

		//debug data
		$this->logger->log('debug', 'Mobbex Helper > createCheckout | Checkout Response: ', $mobbexCheckout->response);

		return $mobbexCheckout->response;
	}

	/**
     * Create checkout when wallet is active,
     *  using a quote instead of an order.
     *  can't use an order object beacouse there is a duplication problem
     * @return bool
     */
    public function createCheckoutFromQuote($quoteData)
    {
		// get customer data
        $customer = [
			'email' => $quoteData['email'], 
            'name' => $quoteData['customer']['firstname'],
            //Customer id added for wallet usage
            'uid' => $quoteData['customer_id'],
            'identification' => $this->customField->getCustomField($quoteData['entity_id'], 'customer', 'dni'),
        ];
		
        if ($quoteData['customer']){
			if ($quoteData['customer']['telephone']) {
				$customer['phone'] = $quoteData['customer']['telephone'];
            }
        }
		
		//get prices
		$orderAmount       = (float) round($quoteData['price'], 2);
        $quote_grand_total = $quoteData['quote']->getGrandTotal();
        
        $items = [];
		
        foreach($quoteData['items'] as $product) {
			
			$subscription = $this->settings->getProductSubscription($product->getId());
			
			if($subscription['enable'] === 'yes'){
				
				$items[] = [
					'type'      => 'subscription',
					'reference' => $subscription['uid']
				];
				
			} else {
				$items[] = array(
					"image"       => (string)Mage::helper('catalog/image')->init($product, 'image')->resize(150), 
					"description" => $product->getName(), 
					"quantity"    => $product->getQtyOrdered(), 
					"total"       => round($product->getPrice(),2) 
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
        
		//Get products active plans
		extract($this->settings->getProductPlans($quoteData['items']));

		try {
			
			$mobbexCheckout = new \Mobbex\Modules\Checkout(
				$quoteData['entity_id'],
				$orderAmount,
				'',
				'',
				$items,
				\Mobbex\Repository::getInstallments($quoteData['items'], $common_plans, $advanced_plans),
				$customer,
				$quoteData['addresses'],
				'mobbexCheckoutRequest'
			);

			//debug data
			$this->logger->log('debug', 'Mobbex Helper > createCheckout | Checkout Response: ', $mobbexCheckout->response);

			return $mobbexCheckout->response;

		} catch (\Exception $e) {
			$this->logger->log('error', $e->getMessage(), isset($e->data) ? $e->data : []);
		}

    }

	/**
	 * Get Addresses data for Mobebx Checkout.
     * 
	 * @param Mage_Sales_Model_Order_Address $address
	 * @return array $addresses
	 */
	public function getAddressData($address)
	{
		if (!$address)
			return;

		$addressData = $address->getData();
		$region = Mage::getModel('directory/region')->load($addressData['region_id'])->getData();

		return [
			'type'         => isset($addressData["address_type"]) ? $addressData["address_type"] : '',
			'country'      => isset($addressData["country_id"]) ? \Mobbex\Repository::convertCountryCode($addressData["country_id"]) : '',
			'street'       => trim(preg_replace('/(\D{0})+(\d*)+$/', '', trim($addressData['street']))),
			'streetNumber' => str_replace(preg_replace('/(\D{0})+(\d*)+$/', '', trim($addressData['street'])), '', trim($addressData['street'])),
			'streetNotes'  => '',
			'zipCode'      => isset($addressData["postcode"]) ? $addressData["postcode"] : '',
			'city'         => isset($addressData["city"]) ? $addressData["city"] : '',
			'state'        => (isset($addressData["country_id"]) && isset($region['code'])) ? str_replace($addressData["country_id"] . '-', '', $region['code']) : ''
		];
	}

	public function getModuleUrl($action, $queryParams) {

		if ($this->settings->get('debug_mode'))
			$queryParams['XDEBUG_SESSION_START'] = 'PHPSTORM';

		return Mage::getUrl('mobbex/payment/' . $action, array('_secure' => true, '_query' => $queryParams)); 
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
            $this->logger->log('error', 'Mobbex Helper > executeHook | Error: ', $e->getMessage());
        }
    }
}