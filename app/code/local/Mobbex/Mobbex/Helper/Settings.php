<?php

class Mobbex_Mobbex_Helper_Settings extends Mage_Core_Helper_Abstract
{
	/** @var Mobbex_Mobbex_Helper_Data */
	public $helper;

	/** @var Mobbex_Mobbex_Model_Customfield */
	public $fields;

	public function __construct()
	{
		// Init class properties
		$this->helper = Mage::helper('mobbex/data');
		$this->fields = Mage::getModel('mobbex/customfield');
	}

    /**
	 * Get advanced plans fields data for
	 * use in product/category config.
     * 
     * @param string $id ID of catalog object.
     * @param string $catalogType Type of catalog object.
	 * 
	 * @return array 
	 */
	public function getCommonPlanFields($id, $catalogType = 'product')
	{
		$result = [];

		// Get sources list from API and current saved configuration from db
		$sources 	  = $this->helper->getSources();
		$checkedPlans = $this->fields->getCustomField($id, $catalogType, 'common_plans') ?: [];

		// Create common plan fields
		foreach ($sources as $source) {
			$plans = !empty($source['installments']['list']) ? $source['installments']['list'] : [];

			foreach ($plans as $plan) {
				$planId	= "common_plan_{$plan['reference']}";

				// Create field data
				$result[$planId] = [
					'label' => $plan['description'] ?: $plan['name'],
					'value' => (!in_array($plan['reference'], $checkedPlans) && $this->fields->getCustomField($id, $catalogType, $plan['reference']) !== 'yes'),
				];
			}
		}

		return $result;
	}

	/**
	 * Get advanced plans fields data for
	 * use in product/category config.
     * 
     * @param string $id ID of catalog object.
     * @param string $catalogType Type of catalog object.
	 * 
	 * @return array 
	 */
	public function getAdvancedPlanFields($id, $catalogType = 'product')
	{
		$result = [];

		// Get sources list from API and current saved configuration from db
		$sources 	  = $this->helper->getSourcesAdvanced();
		$checkedPlans = $this->fields->getCustomField($id, $catalogType, 'advanced_plans') ?: [];

		// Create advanced plan fields
		foreach ($sources as $source) {
			$plans      = !empty($source['installments']) ? $source['installments'] : [];
			$reference  = $source['source']['reference'];
			$sourceName = $source['source']['name'];

			foreach ($plans as $plan) {
				$planId	= "advanced_plan_{$plan['uid']}";

				// Create field data
				$result[$reference][$sourceName][$planId] = [
					'label' => $plan['description'] ?: $plan['name'],
					'value' => (is_array($checkedPlans) && in_array($plan['uid'], $checkedPlans)),
				];
			}
		}

		return $result;
	}

	public function getProductSubscription($id)
	{
		$subscription = [
			'enable' => $this->fields->getCustomField($id, 'product', 'subscription_enable') ?: 'no',
			'uid'    => $this->fields->getCustomField($id, 'product', 'subscription_uid') ?: ''
		];

		return $subscription;
	}

	/**
	 * Get entity an entity assigned to a product or category.
	 * 
	 * @param mixed $id 
	 * @param string $catalogType 
	 * 
	 * @return string 
	 */
	public function getEntity($id, $catalogType = 'product')
	{
		return $this->fields->getCustomField($id, $catalogType, 'entity') ?: '';
	}

	/**
	 * Reiceives a product object & return the entity asigned to
	 * if product didnt have an entity assigned, return the entity of his category.
	 * 
	 * @param object $product
	 * 
	 * @return string $entity 
	 */
	public function getProductEntity($product)
	{
        if($this->fields->getCustomField($product->getProductId(), 'product', 'entity'))
            return $this->fields->getCustomField($product->getProductId(), 'product', 'entity');

        $categories = $product->getCategoryIds();
        if($categories)
            return $this->fields->getCustomField($categories[0], 'category', 'entity'); 

        return '';
	}

    /**
     * Get the merchants from item list.
     * @param array
     * @return array
     */
    public function getMerchants($items)
    {
        $merchants = [];

        //Get the merchants from items list
        foreach ($items as $item) {
            if (!empty($item['entity']))
                $merchants[] = ['uid' => $item['entity']];
        }

        return $merchants;
	}

	/**
	 * Save plan filter fields of product/category.
	 * 
	 * @param mixed $id 
	 * @param string $catalogType 
	 */
	public function savePlanFields($id, $catalogType = 'product')
	{
		$common_plans = $advanced_plans = [];

		// Remove values saved with previus method
		foreach (Mobbex_Mobbex_Helper_Data::$ahora as $plan) {
			$planId = $this->fields->getCustomField($id, $catalogType, $plan, 'customfield_id');

			if ($planId) {
				$this->fields->load($planId);
				$this->fields->delete();
			}
		}

		// Get posted values
		foreach ($_POST as $key => $value) {
			if (strpos($key, 'common_plan_') !== false && $value === 'no') {
				$uid = explode('common_plan_', $key)[1];
				$common_plans[] = $uid;
			} else if (strpos($key, 'advanced_plan_') !== false && $value === 'on'){
				$uid = explode('advanced_plan_', $key)[1];
				$advanced_plans[] = $uid;
			}
		}

		// Save data
		$this->fields->saveCustomField($id, $catalogType, 'common_plans', $common_plans);
		$this->fields->saveCustomField($id, $catalogType, 'advanced_plans', $advanced_plans);

		return true;
	}

	public function saveProductSubscription($id)
	{
		$this->fields->saveCustomField($id, 'product', 'subscription_enable', isset($_POST['sub_enable']) ? $_POST['sub_enable'] : '');
		$this->fields->saveCustomField($id, 'product', 'subscription_uid', isset($_POST['sub_uid']) ? $_POST['sub_uid'] : '');

		return true;
	}

	public function saveEntity($id, $catalogType = 'product')
	{
		$this->fields->saveCustomField($id, $catalogType, 'entity', isset($_POST['entity']) ? $_POST['entity'] : '');

		return true;
	}

}