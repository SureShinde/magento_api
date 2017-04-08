<?php

class Bzaikia_Api_Model_Api2_Category_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Category
{
    const ROOT_CAT = 1;

    public function _retrieveCollection()
    {
        $result = array();

        $this->collectChild(self::ROOT_CAT, $result);

        $return = $this->_removeKey($result);
        return $return;
    }

    /**
     * @param $categoryId
     * @param $result
     */
    public function collectChild($categoryId, &$result)
    {
        $categoryCollection = Mage::getModel('catalog/category')->getCollection();
        $categoryCollection->addAttributeToSelect(array('name', 'image'));
        $categoryCollection->addFieldToFilter('parent_id', array('eq' => $categoryId));
        if (!empty($categoryCollection->getSize())) {
            foreach ($categoryCollection as $category) {
                $result[$category->getId()] = array(
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                    'image' => $category->getImageUrl() ? $category->getImageUrl() : null,
                    'product_count' => $this->_getProductCount($category->getId()),
                    'child' => array()
                );
                $this->collectChild($category->getId(), $result[$category->getId()]['child']);
            }
        }
    }

    /**
     * @param $catId
     * @return int
     */
    protected function _getProductCount($catId)
    {
        $resource = Mage::getSingleton('core/resource');
        /**
         * @var $writeAdapter Magento_Db_Adapter_Pdo_Mysql
         */
        $writeAdapter = $resource->getConnection('core_write');

        $select = $writeAdapter->select()
            ->from($resource->getTableName('catalog_category_product'))
            ->where('category_id = ?', $catId);
        $result = $writeAdapter->fetchAll($select);
        return count($result);
    }

    /**
     * @param $categories
     * @return array
     */
    protected function _removeKey($categories) {
        $result = array();
        foreach ($categories as $id => $category) {
            if (!empty($category['child'])) {
                $category['child'] = $this->_removeKey($category['child']);
            }
            $result[] = $category;
        }
        return $result;
    }
}