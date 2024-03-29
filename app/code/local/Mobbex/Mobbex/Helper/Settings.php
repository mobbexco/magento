<?php

class Mobbex_Mobbex_Helper_Settings extends Mage_Core_Helper_Abstract
{
	/** @var Mobbex_Mobbex_Helper_Instantiator */
	public $instantiator;

	/** Module configuration paths */
	public $settingPaths = [
		'enabled'                 => 'payment/mobbex/active',
		'title'                   => 'payment/mobbex/title',
		'timeout'                 => 'payment/mobbex/timeout',
		'api_key'                 => 'payment/mobbex/api_key',
		'access_token'            => 'payment/mobbex/access_token',
		'site_id'                 => 'payment/mobbex/site_id',
		'test'                    => 'payment/mobbex/test_mode',
		'debug_mode'              => 'payment/mobbex/debug_mode',
		'embed'                   => 'payment/mobbex/embed',
		'wallet'                  => 'payment/mobbex/wallet',
		'multicard'               => 'payment/mobbex/multicard',
		'multivendor'             => 'payment/mobbex/multivendor',
		'payment_mode'            => 'payment/mobbex/payment_mode',
		'order_status'            => 'payment/mobbex/order_status',
		'alllowspecific'          => 'payment/mobbex/alllowspecific',
		'specificcountry'         => 'payment/mobbex/specificcountry',
		'sort_order'              => 'payment/mobbex/sort_order',
		'financing_product'       => 'payment/mobbex/financing_product',
		'financing_cart'          => 'payment/mobbex/financing_cart',
		'theme'                   => 'payment/mobbex/theme',
		'color'                   => 'payment/mobbex/primary_color',
		'background'              => 'payment/mobbex/background_color',
		'button_logo'             => 'payment/mobbex/button_logo',
		'button_text'             => 'payment/mobbex/button_text',
		'widget_style'            => 'payment/mobbex/widget_style',
		'order_status_approved'   => 'payment/mobbex/order_status_approved',
		'order_status_in_process' => 'payment/mobbex/order_status_in_process',
		'order_status_cancelled'  => 'payment/mobbex/order_status_cancelled',
		'order_status_refunded'   => 'payment/mobbex/order_status_refunded',
	];

	/** Mobbex Catalog Settings */
	public $catalogSettings = ['common_plans', 'advanced_plans', 'entity', 'is_subscription', 'subscription_uid'];

	public function __construct()
	{
		// Init class properties
		\Mage::helper('mobbex/instantiator')->setProperties($this, ['customField']);
		$this->fields = \Mage::getModel('mobbex/customfield');
	}

	/**
	 * Get a config value from db.
	 * 
	 * @param string $path Config identifier. @see $this::$configurationPaths
	 * @param string $store Store code.
	 * 
	 * @return mixed
	 */
	public function get($name)
	{
		return \Mage::getStoreConfig($this->settingPaths[$name]);
	}

	/**
	 * Get all module configuration values from db.
	 * 
	 * @return array
	 */
	public function getAll()
	{
		$settings = [];
		foreach ($this->settingPaths as $name => $value)
			$settings[$name] = $this->get($name);

		return $settings;
	}

	/** CATALOG SETTINGS */

	/**
	 * Retrieve the given product/category option.
	 * 
	 * @param int|string $id
	 * @param string $object
	 * @param string $catalogType
	 * 
	 * @return array|string
	 */
	public function getCatalogSetting($id, $object, $catalogType = 'product')
	{
		$setting = $this->customField->getCustomField($id, $catalogType, $object);

		if (strpos($object, '_plans'))
			return $setting && is_string($setting) ? json_decode($setting, true) : [];

		return $setting ?: '';
	}

	/**
	 * Save mobbex configuration for a given product or category.
	 * 
	 * @param int|string $id
	 * @param string $catalogType
	 */
	public function saveCatalogSettings($id, $catalogType = 'product')
	{
		$configs = [
			'entity'         => isset($_POST['entity']) ? $_POST['entity'] : '',
			'common_plans'   => [],
			'advanced_plans' => []
		];

		if($catalogType === 'product'){
			$configs["is_subscription"] = isset($_POST['sub_enable']) ? $_POST['sub_enable'] : '';
			$configs["subscription_uid"]    = isset($_POST['sub_uid']) ? $_POST['sub_uid'] : '';
		}

		//Get Plans
		foreach ($_POST as $key => $value) {
			if (strpos($key, 'common_plan_') !== false && $value === 'no') {
				$uid = explode('common_plan_', $key)[1];
				$configs['common_plans'][] = $uid;
			} else if (strpos($key, 'advanced_plan_') !== false && $value === 'on') {
				$uid = explode('advanced_plan_', $key)[1];
				$configs['advanced_plans'][] = $uid;
			}
		}

		foreach ($configs as $key => $value)
			$this->customField->saveCustomField($id, $catalogType, $key, $value);
	}

	/** CATALOG SETTINGS */

	/**
	 * Get active plans for a given products.
	 * @param array $products
	 * @return array $array
	 */
	public function getProductPlans($products)
	{
		$common_plans = $advanced_plans = [];

		foreach ($products as $product) {
			foreach (['common_plans', 'advanced_plans'] as $value) {
				//Get product active plans
				${$value} = array_merge($this->getCatalogSetting($product->getId(), $value), ${$value});
				//Get product category active plans
				foreach ($product->getCategoryIds() as $categoryId)
					${$value} = array_unique(array_merge(${$value}, $this->getCatalogSetting($categoryId, $value, 'category')));
			}
		}

		return compact('common_plans', 'advanced_plans');
	}

	/**
	 * Get the entity of a specific product
	 * 
	 * @param object $product
	 * 
	 * @return string $entity
	 */
	public function getProductEntity($product)
	{
		if ($this->getCatalogSetting($product->getId(), 'entity'))
		return $this->getCatalogSetting($product->getId(), 'entity');

		$categories = $product->getCategoryIds();
		foreach ($categories as $category) {
			if ($this->getCatalogSetting($category, 'entity', 'category'))
			return $this->getCatalogSetting($category, 'entity', 'category');
		}

		return '';
	}

	/**
	 * Retrieve specific product subscription data.
	 * 
	 * @param int|string $id
	 * 
	 * @return array
	 */
	public function getProductSubscription($id)
	{
		foreach (['is_subscription', 'subscription_uid'] as $value)
			${$value} = $this->getCatalogSetting($id, $value);

		return ['enable' => $is_subscription, 'uid' => $subscription_uid];
	}
}