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

    /**
     * Get data from childs node.
     * 
     * @param array $childs
     * @param string $orderId
     * 
     * @return array
     */
    public function getMobbexChilds($childs, $orderId)
    {
        foreach ($childs as $child)
            $childsData[] = $this->formatWebhookData($child, $orderId);

        return $childsData;
    }

    /**
     * Check if webhook is parent type using him payment id.
     * 
     * @param string $paymentId
     * 
     * @return bool
     */
    public function isParent($paymentId)
    {
        return strpos($paymentId, 'CHD-') !== 0;
    }

    /**
     * Format the webhook data in an array.
     * 
     * @param array $webhook_data
     * @param string $orderId
     * @param bool $multicard
     * @param bool $multivendor
     * @return array $data
     */
    public function formatWebhookData($webhookData, $orderId)
    {
        $data = [
            'order_id'           => $orderId,
            'parent'             => isset($webhookData['payment']['id']) ? $this->isParent($webhookData['payment']['id']) : false,
            'childs'             => isset($webhookData['childs']) ? json_encode($webhookData['childs']) : '',
            'operation_type'     => isset($webhookData['payment']['operation']['type']) ? $webhookData['payment']['operation']['type'] : '',
            'payment_id'         => isset($webhookData['payment']['id']) ? $webhookData['payment']['id'] : '',
            'description'        => isset($webhookData['payment']['description']) ? $webhookData['payment']['description'] : '',
            'status_code'        => isset($webhookData['payment']['status']['code']) ? $webhookData['payment']['status']['code'] : '',
            'status_message'     => isset($webhookData['payment']['status']['message']) ? $webhookData['payment']['status']['message'] : '',
            'source_name'        => isset($webhookData['payment']['source']['name']) ? $webhookData['payment']['source']['name'] : 'Mobbex',
            'source_type'        => isset($webhookData['payment']['source']['type']) ? $webhookData['payment']['source']['type'] : 'Mobbex',
            'source_reference'   => isset($webhookData['payment']['source']['reference']) ? $webhookData['payment']['source']['reference'] : '',
            'source_number'      => isset($webhookData['payment']['source']['number']) ? $webhookData['payment']['source']['number'] : '',
            'source_expiration'  => isset($webhookData['payment']['source']['expiration']) ? json_encode($webhookData['payment']['source']['expiration']) : '',
            'source_installment' => isset($webhookData['payment']['source']['installment']) ? json_encode($webhookData['payment']['source']['installment']) : '',
            'installment_name'   => isset($webhookData['payment']['source']['installment']['description']) ? json_encode($webhookData['payment']['source']['installment']['description']) : '',
            'installment_amount' => isset($webhookData['payment']['source']['installment']['amount']) ? $webhookData['payment']['source']['installment']['amount'] : '',
            'installment_count'  => isset($webhookData['payment']['source']['installment']['count']) ? $webhookData['payment']['source']['installment']['count'] : '',
            'source_url'         => isset($webhookData['payment']['source']['url']) ? json_encode($webhookData['payment']['source']['url']) : '',
            'cardholder'         => isset($webhookData['payment']['source']['cardholder']) ? json_encode(($webhookData['payment']['source']['cardholder'])) : '',
            'entity_name'        => isset($webhookData['entity']['name']) ? $webhookData['entity']['name'] : '',
            'entity_uid'         => isset($webhookData['entity']['uid']) ? $webhookData['entity']['uid'] : '',
            'customer'           => isset($webhookData['customer']) ? json_encode($webhookData['customer']) : '',
            'checkout_uid'       => isset($webhookData['checkout']['uid']) ? $webhookData['checkout']['uid'] : '',
            'total'              => isset($webhookData['payment']['total']) ? $webhookData['payment']['total'] : '',
            'currency'           => isset($webhookData['checkout']['currency']) ? $webhookData['checkout']['currency'] : '',
            'risk_analysis'      => isset($webhookData['payment']['riskAnalysis']['level']) ? $webhookData['payment']['riskAnalysis']['level'] : '',
            'data'               => isset($webhookData) ? json_encode($webhookData) : '',
            'created'            => isset($webhookData['payment']['created']) ? $webhookData['payment']['created'] : '',
            'updated'            => isset($webhookData['payment']['updated']) ? $webhookData['payment']['created'] : '',
            'user'               => [
                'name' => isset($webhookData['user']['name']) ? $webhookData['user']['name'] : '',
                'email' => isset($webhookData['user']['email']) ? $webhookData['user']['email'] : '',
            ],
        ];
        Mage::log($data, null, 'log.log', true);
        return $data;
    }
}