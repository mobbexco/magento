<?php
 
class Mobbex_Mobbex_Model_Transaction extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('mobbex/transaction');
    }

    /**
     * Get custom transaction data.
     * 
     * @param array $filter  ['column_name' => 'value']
     * 
     * @return array
     */
    public function getMobbexTransaction($filter = [])
    {
        //Get the model collection
        $collection = $this->getCollection();
        //Filter the data
        foreach ($filter as $key => $value)
            $collection->addFieldToFilter($key, $value);
        //Get model data
        $data = isset($filter['parent']) && isset($collection->getData()[0]) && $filter['parent'] ? $collection->getData()[0] : $collection->getData();

        return !empty($data) ? $data : false;
    }

    /**
     * Saves a Transaction
     * 
     * @param int $order_id
     * @param array $data
     * 
     * @return boolean
     */
    public function saveMobbexTransaction($data)
    {
        //Get model
        $transaction = new Mobbex_Mobbex_Model_Transaction();

        //Set transaction data in the model
        foreach ($data as $key => $value)
            $transaction->setData($key, $value);

        return $transaction->save();
    }
}