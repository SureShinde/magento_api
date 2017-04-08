<?php

class Bzaikia_Api_Model_Api2_Brand_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Category
{
    const PARAM_CATEGORY = 'category';
    const PARAM_NAME = 'name';

    /**
     * @return array
     */
    public function _retrieveCollection()
    {
        $categoryId = addslashes($this->getRequest()->getParam(self::PARAM_CATEGORY));
        $brandName = addslashes($this->getRequest()->getParam(self::PARAM_NAME));
        $resource = Mage::getSingleton('core/resource');

        $brandSelect = $this->_getReadAdapter()->select()
            ->from($resource->getTableName('manufacturer'), array('id', 'name'))
            ->where('name like "%' . $brandName . '%" ');

        if (!empty($categoryId)) {
            $brandIds = $this->_getBrands($categoryId);
            if ($brandIds) {
                $brandSelect->where('id in (' . implode(',', $brandIds) . ')');
            } else {
                return array();
            }
        }

        $brands = $this->_getReadAdapter()->fetchAll($brandSelect);
        return $brands;
    }

    /**
     * @param $categoryId
     * @return array|bool
     */
    protected function _getBrands($categoryId)
    {
        $helper = Mage::helper('bzaikia_api');
        if ($categoryProducts = $helper->getProductFromCategory($categoryId)) {
            $productCollection = Mage::getModel('catalog/product')->getCollection();
            $productCollection->addAttributeToFilter('status', 1); // enabled
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($productCollection);
            $productCollection->addFieldToFilter('entity_id', array('in' => $categoryProducts));

            if ($productIds = $productCollection->getAllIds()) {
                $resource = Mage::getSingleton('core/resource');
                $productSelect = $this->_getReadAdapter()->select()
                    ->from($resource->getTableName('manufacturer_product'), 'manufacturer_id')
                    ->where('product_id in (' . implode(',', $productIds) . ')');
                $brandIds = $this->_getReadAdapter()->fetchCol($productSelect);

                return $brandIds;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @return Magento_Db_Adapter_Pdo_Mysql
     */
    protected function _getReadAdapter()
    {
        $resource = Mage::getSingleton('core/resource');
        /**
         * @var $readAdapter Magento_Db_Adapter_Pdo_Mysql
         */
        $readAdapter = $resource->getConnection('core_write');

        return $readAdapter;
    }

    protected function _validate($categoryId, $brandName)
    {
        if (empty($categoryId) || empty($brandName)) {
            return self::ERROR_MESSAGE_LACKING_PARAM;
        }

    }
}