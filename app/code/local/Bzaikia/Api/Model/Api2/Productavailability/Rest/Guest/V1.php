<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Productavailability_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Productavailability
{
    public function _retrieveCollection()
    {
        $sku = $this->getRequest()->getParam('sku');
        $skues = explode(',', $sku);
        $productCollection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('price')
            ->addFieldToFilter('sku', array('in' => $skues));

        $result = array();

        foreach ($productCollection as $product) {
            $stockItem = Mage::getModel('cataloginventory/stock_item')
                ->loadByProduct($product);
            $result[] = array(
                'sku' => $product->getSku(),
                'price' => $product->getFinalPrice(),
                'available_stock' => intval($stockItem->getQty())
            );
        }

        return $result;
    }
}