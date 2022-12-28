<?php
 
class Mobbex_Mobbex_Model_Customfield extends Mage_Core_Model_Abstract
{
    protected function _construct()
    {
        $this->_init('mobbex/customfield');
    }

    /**
     * Get custom field data
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * @param string $data
     * @param string $searched_column
     * 
     * @return string
     */
    public function getCustomField($row_id, $object, $field_name, $searched_column = 'data')
    {

        $collection = $this->getCollection()
            ->addFieldToFilter('row_id', $row_id)
            ->addFieldToFilter('object', $object)
            ->addFieldToFilter('field_name', $field_name)
            ->getColumnValues($searched_column);

        return $collection[0];
    }

    /**
     * Saves custom field
     * 
     * @param int $row_id
     * @param string $object
     * @param string $field_name
     * @param string $data
     * 
     * @return boolean
     */
    public function saveCustomField($row_id, $object, $field_name, $data)
    {
        // Previus record
        $previous_id = $this->getCustomField($row_id, $object, $field_name, 'customfield_id');
        
        // Instantiate if record previously exists
        $custom_field = $previous_id ? $this->load($previous_id) : new Mobbex_Mobbex_Model_Customfield();

        $custom_field->setData('row_id', $row_id);
        $custom_field->setData('object', $object);
        $custom_field->setData('field_name', $field_name);
        $custom_field->setData('data', is_array($data) ? json_encode($data) : $data);

        return $custom_field->save();
    }
}