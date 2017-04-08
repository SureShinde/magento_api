<?php

class Bzaikia_Api_Model_Api2_Product_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Category
{
    protected $_productData = array();

    /**
     * @return array
     */
    public function _retrieve()
    {
        $block = Mage::app()->getLayout()->createBlock('bzaikia_api/product_view_type_configurable');
        $sku = $this->getRequest()->getParam('sku');
        $result = array();
        $product = $this->_getProduct($sku);

        if ($product && $product->getId()) {
            $result['name'] = $product->getName();
            $result['sku'] = $product->getSku();
            $result['image'] = $product->getImageUrl();
            $result['description'] = $product->getDescription();
            $result['brand'] = Mage::helper('bzaikia_api')->getBrandName($product->getId());
            if ($product->getTypeId() == 'bundle') {
                $result['price'] = array_pop($product->getPriceModel()->getTotalPrices($product));
            } else {
                $result['price'] = $product->getFinalPrice();
            }
            $result['price_unit'] = Mage::app()->getStore()->getCurrentCurrencyCode();
            $result['servings_count'] = $product->getData('servings_count');
            $result['nutri_info_image'] = $product->getData('nutri_info_image');
            $result['nutri_info_text'] = $product->getData('nutri_info_text');
            $result['nutri_info_details'] = $product->getData('nutri_info_details');
            if ($product->getTypeId() == 'configurable') {
                $block->setProduct($product);
                $result['option'] = Mage::helper('core')->jsonDecode($block->getJsonConfig());
            }
            if ($attr = $this->getRequest()->getParam('attr')) {
                $this->_productData = array_merge($this->_productData, explode(',', $attr));
            }
            foreach ($this->_productData as $data) {
                $result[$data] = $product->{'get' . $data}();
            }
        }
        return array('data' => $result);
    }

    /**
     * @param $sku
     * @return bool|Mage_Core_Model_Abstract
     */
    protected function _getProduct($sku)
    {
        $product = Mage::getModel('catalog/product')->loadByAttribute('sku', $sku);
        if (!$product || !$product->getId()) {
            $product = Mage::getModel('catalog/product')->load($sku);
            if (!$product || !$product->getId())
                return false;
        }
        return $product;
    }
}