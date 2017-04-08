<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Helper_Data extends Mage_Core_Helper_Abstract
{
    const CURRENT_STORE = 1;
    const SERVER_ERROR_CODE = 500;
    const CLIENT_ERROR_CODE = 400;
    const AUTHORIZATION_VALIDATION_FAIL_CODE = 403;
    const SUCCESSFUL_CODE = 200;
    /**
     * @param $categoryId
     * @return array
     */
    public function getProductFromCategory($categoryId)
    {
        $resource = Mage::getSingleton('core/resource');

        $select = $this->_getReadAdapter()->select()
            ->from($resource->getTableName('catalog_category_product'), 'product_id')
            ->where('category_id = ? ', $categoryId);
        $result = $this->_getReadAdapter()->fetchCol($select);

        return $result;
    }

    /**
     * @param $brandId
     * @return array
     */
    public function getProductFromBrand($brandId)
    {
        $collection = Mage::getSingleton('manufacturer/provider_product')
            ->getProductCollection($brandId);

        return $collection->getAllIds();
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

    /**
     * @param $productId
     * @return string
     */
    public function getBrandName($productId)
    {
        try {
            $resource = Mage::getSingleton('core/resource');
            $select = $this->_getReadAdapter()->select()->from(array('mp' => $resource->getTableName('manufacturer/product')), '')
                ->join(array('m' => $resource->getTableName('manufacturer/manufacturer')), 'm.id = mp.manufacturer_id AND mp.product_id = ' . $productId, 'm.name');
            $result = $this->_getReadAdapter()->fetchOne($select);

            return $result;
        } catch (Exception $e) {
            return '';
        }
    }
}