<?php

class Mobbex_Mobbex_Helper_Settings extends Mage_Core_Helper_Abstract
{
	/** @var Mobbex_Mobbex_Helper_Instantiator */
	public $instantiator;

	/** Module configuration paths */
	public $settingPaths = [
		'enabled'                 => 'payment/mobbex/active',
		'title'                   => 'payment/mobbex/title',
		'api_key'                 => 'payment/mobbex/api_key',
		'access_token'            => 'payment/mobbex/access_token',
		'test'                    => 'payment/mobbex/test_mode',
		'debug_mode'              => 'payment/mobbex/debug_mode',
		'embed'                   => 'payment/mobbex/embed',
		'wallet'                  => 'payment/mobbex/wallet',
		'multicard'               => 'payment/mobbex/multicard',
		'multivendor'             => 'payment/mobbex/multivendor',
		'order_status'            => 'payment/mobbex/order_status',
		'alllowspecific'          => 'payment/mobbex/alllowspecific',
		'specificcountry'         => 'payment/mobbex/specificcountry',
		'sort_order'              => 'payment/mobbex/sort_order',
		'financing_product'       => 'payment/mobbex/financing_product',
		'financing_cart'          => 'payment/mobbex/financing_cart',
		'tax_id'                  => 'payment/mobbex/taxid',
		'theme_type'              => 'payment/mobbex/theme',
		'primary_color'           => 'payment/mobbex/primary_color',
		'background_color'        => 'payment/mobbex/background_color',
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
		$this->helper = \Mage::helper('mobbex/data');
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
		if (strpos($object, '_plans'))
			return unserialize($this->customField->getCustomField($id, $catalogType, $object)) ?: [];

		return $this->customField->getCustomField($id, $catalogType, $object) ?: '';
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

		foreach (['advanced_plans', 'common_plans'] as $plan)
			$configs[$plan] = serialize($configs[$plan]);

		foreach ($configs as $key => $value)
			$this->customField->saveCustomField($id, 'product', $key, $value);
	}

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
}