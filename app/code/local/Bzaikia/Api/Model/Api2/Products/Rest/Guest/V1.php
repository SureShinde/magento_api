<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Products_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Products
{
    protected $_availableSorting;
    protected $_selectedAttribute;


    protected function _init()
    {
        $this->_availableSorting = array(
            'name_asc' => array(
                'attribute' => 'name',
                'dir' => 'asc'
            ),
            'name_desc' => array(
                'attribute' => 'name',
                'dir' => 'desc'
            ),
            'price_asc' => array(
                'attribute' => 'price',
                'dir' => 'asc'
            ),
            'price_desc' => array(
                'attribute' => 'price',
                'dir' => 'desc'
            )
        );

        $this->_selectedAttribute = array(
            array(
                'name',
                'image',
                'price',
                'short_description',
                'manufacturer',
                'weight',
                'flavour'
            )
        );

        /**
         * data of returning product for getting product detail call
         */
        $this->_productData = array(
            'name',
            'description',
            'imageUrl',
            'price',
            'servings_count',
            'nutri_info_image',
            'nutri_info_text',
            'nutri_info_details'
        );
    }

    public function __construct()
    {
        $this->_init();
    }

    /**
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    protected function _initProductCollection()
    {
        $productCollection = Mage::getModel('catalog/product')->getCollection();
        $productCollection->addAttributeToSelect($this->_selectedAttribute);
        $productCollection->addAttributeToFilter('status', 1); // enabled
        $productCollection->addAttributeToFilter('visibility', array('neq' => 1));
        Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($productCollection);
        $this->_addManufactureNameToCollection($productCollection);

        return $productCollection;
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
     * @param $collection
     */
    protected function _applySorting($collection)
    {
        $sort = $this->getRequest()->getParam('sort');
        if (in_array($sort, array_keys($this->_availableSorting))) {
            $sortInfo = $this->_availableSorting[$sort];
            $collection->addAttributeToSort($sortInfo['attribute'], $sortInfo['dir']);
        }
    }

    /**
     * @param Varien_Data_Collection_Db $collection
     * @return $this
     */
    protected function _applyFilter($collection)
    {
        $categoryId = $this->getRequest()->getParam('category');
        $branchId = $this->getRequest()->getParam('brand');
        $helper = Mage::helper('bzaikia_api');
        $restrictedIds = array();
        if ($categoryId && intval($categoryId) && $branchId && intval($branchId)) {
            $restrictedIds = array_intersect(
                $helper->getProductFromCategory($categoryId), $helper->getProductFromBrand($branchId)
            );

        } else if ($categoryId && intval($categoryId)) {
            $restrictedIds = $helper->getProductFromCategory($categoryId);
        } else if ($branchId && intval($branchId)) {
            $restrictedIds = $helper->getProductFromBrand($branchId);
        }

        if ($restrictedIds) {
            $collection->addFieldToFilter('entity_id', array('in' => $restrictedIds));
        }

        return $this;
    }

    /**
     * @param $collection
     */
    protected function _applyLimit($collection)
    {
        $limit = $this->getRequest()->getParam('limit');
        $offset = $this->getRequest()->getParam('offset');

        if ($limit && intval($limit)) {
            if (!$offset) {
                $offset = 0;
            }
            $collection->getSelect()->limit($limit, intval($offset));
        }
    }

    /**
     * @param $collection
     */
    protected function _applySearching($collection)
    {
        if ($search = $this->getRequest()->getParam('search')) {
            $collection->addAttributeToFilter('name', array('like' => '%' . $search . '%'));
        }
    }

    /**
     * @param $collection
     */
    protected function _addManufactureNameToCollection($collection)
    {
        try {
            $resource = Mage::getSingleton('core/resource');
            $select = $this->_getReadAdapter()->select()->from(array('mp' => $resource->getTableName('manufacturer/product')))
                ->joinLeft(array('m' => $resource->getTableName('manufacturer/manufacturer')), 'm.id = mp.manufacturer_id', 'm.name');

            $collection->getSelect()->joinLeft(
                array(
                    'manu' => new Zend_Db_Expr('(' . $select->__toString() . ')')
                ),
                'e.entity_id = manu.product_id',
                array(
                    'brand_name' => new Zend_Db_Expr('GROUP_CONCAT(`manu`.`name`)')
                )
            );
            $collection->getSelect()->group('entity_id');
        } catch (Exception $e) {

        }

    }

    public function _retrieve()
    {
        $this->_init();
        $productCollection = $this->_initProductCollection();
        $this->_applySearching($productCollection);
        $this->_applyFilter($productCollection);
        $count = $productCollection->getSize();
        $this->_applySorting($productCollection);
        $this->_applyLimit($productCollection);
        $result = array(
            'limit' => $this->getRequest()->getParam('limit'),
            'count' => $count,
            'products' => array()
        );

        foreach ($productCollection as $product) {
            $data = array(
                'name' => $product->getName(),
                'sku' => $product->getSku(),
                'price' => $product->getFinalPrice(),
                'image' => $product->getImageUrl(),
                'short_description' => $product->getShortDescription(),
                'weight' => $product->getWeight(),
                'flavour' => $product->getAttributeText('flavour'),
                'brand_name' => $product->getData('brand_name'),
                'price_unit' => Mage::app()->getStore()->getCurrentCurrencyCode()
            );


            $result['products'][] = $data;
        }
        return $result;
    }
}