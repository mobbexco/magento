<?php
 
class Mobbex_Mobbex_Model_Transaction extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('mobbex/transaction');
    }

    /**
     * Get custom transaction data
     * 
     * @param int $order_id
     * @param bool $filter
     * 
     * @return array
     */
    public function getMobbexTransaction($order_id, $filter = [false])
    {
        if($filter[0]){
            $collection = $this->getCollection()
                ->addFieldToFilter('order_id', $order_id)
                ->addFieldToFilter('parent', $filter[1])
                ->getData();

            if($filter[1])
                $collection = isset($collection[0]) ? $collection[0] : $collection;

            return !empty($collection) ? $collection : false;
        }
        $collection = $this->getCollection()
            ->addFieldToFilter('order_id', $order_id)
            ->getData();

        return !empty($collection) ? $collection : false;
    }

    /**
     * Saves a Transaction
     * 
     * @param int $order_id
     * @param string $data
     * 
     * @return boolean
     */
    public function saveMobbexTransaction($data)
    {
        //Get model
        $transaction = new Mobbex_Mobbex_Model_Transaction();

        //Save data in mobbex transaction table
        $transaction->setData('order_id', $data['order_id']);
        $transaction->setData('parent', $data['parent']);
        $transaction->setData('operation_type', $data['operation_type']);
        $transaction->setData('payment_id', $data['payment_id']);
        $transaction->setData('description', $data['description']);
        $transaction->setData('status_code', $data['status_code']);
        $transaction->setData('status_message', $data['status_message']);
        $transaction->setData('source_name', $data['source_name']);
        $transaction->setData('source_type', $data['source_type']);
        $transaction->setData('source_reference', $data['source_reference']);
        $transaction->setData('source_number', $data['source_number']);
        $transaction->setData('source_expiration', $data['source_expiration']);
        $transaction->setData('source_url', $data['source_url']);
        $transaction->setData('source_installment', $data['source_installment']);
        $transaction->setData('installment_name', $data['installment_name']);
        $transaction->setData('installment_amount', $data['installment_amount']);
        $transaction->setData('installment_count', $data['installment_count']);
        $transaction->setData('cardholder', $data['cardholder']);
        $transaction->setData('entity_name', $data['entity_name']);
        $transaction->setData('entity_uid', $data['entity_uid']);
        $transaction->setData('customer', $data['customer']);
        $transaction->setData('checkout_uid', $data['checkout_uid']);
        $transaction->setData('total', $data['total']);
        $transaction->setData('currency', $data['currency']);
        $transaction->setData('risk_analysis', $data['risk_analysis']);
        $transaction->setData('data', $data['data']);
        $transaction->setData('created', $data['created']);
        $transaction->setData('updated', $data['updated']);

        return $transaction->save();
    }
}