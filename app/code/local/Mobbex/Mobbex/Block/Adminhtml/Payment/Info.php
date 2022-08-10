<?php class Mobbex_Mobbex_Block_Adminhtml_Payment_Info extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mobbex/info.phtml');

        $this->mobbexTransaction = Mage::getModel('mobbex/transaction');
    }

    /**
     * Eliminates duplicated cards.
     * @param array
     * @return array
     */
    public function filterCards($cards)
    {
        if(!$cards)
            return false;
        $filter = [];
        $data = [];
        foreach ($cards as $key => $card) {
            $filter['_' . $key] = $card['payment_id'];
        }
        $filter = array_unique($filter);
        foreach ($cards as $key => $card) {
            $pos = '_' . $key;
            if (isset($filter[$pos])) {
                $data[] = $card;
            }
        }
        return $data;
    }
}
