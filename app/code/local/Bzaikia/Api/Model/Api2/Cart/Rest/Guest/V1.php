<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart
{

    public function _create($filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $this->setCustomerId($filteredData['user_id']);
        $userId = $this->getCustomerId();
        if ($this->_validateAuthorization($userId)) {
            if (!empty($userId)) {
                $this->getResponse()->setHttpResponseCode(self::CLIENT_ERROR_CODE);
            }
            if ($this->_getQuote($userId)) {
                $this->getResponse()->setHttpResponseCode(self::SUCCESSFUL_CODE);
                return;
            }
            $this->getResponse()->setHttpResponseCode(self::SERVER_ERROR_CODE);
        }

        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }


    public function _retrieveCollection()
    {
        $userId = $this->getRequest()->getParam('user_id');
        $this->setCustomerId($userId);
        if ($this->_validateAuthorization($userId)) {
            $quote = $this->_getQuote($userId);
            $data = array(
                'cart_id' => $quote->getId(),
                'subtotal' => round($quote->getSubtotal(), 2),
                'shipping' => round($quote->getShippingAddress()->getShippingAmount(), 2),
                'discount' => round($quote->getShippingAddress()->getDiscountAmount(), 2),
                'external_bundle_id' => $quote->getData('external_bundle_id'),
                'total' => round($quote->getGrandTotal(), 2),
                'shipping_method_id' => $quote->getShippingAddress()->getShippingMethod(),
                'shipping_method_name' => $quote->getShippingAddress()->getShippingDescription(),
                'items' => array(),
            );
            $items = array();
            /**
             * @var $item Mage_Sales_Model_Quote_Item
             */
            foreach ($quote->getAllVisibleItems() as $item) {
                $product = $item->getProduct();
                $productImage = Mage::getResourceModel('catalog/product')->getAttributeRawValue($product->getId(), 'image');
                $itemData = array(
                    'quote_item_id' => $item->getId(),
                    'product_name' => $product->getName(),
                    'sku' => $product->getSku(),
                    'product_image' => Mage::getSingleton('catalog/product_media_config')->getMediaUrl($productImage),
                    'brand_name' => $this->_getBrandName($product->getId()),
                    'quantity' => $item->getQty(),
                    'price_per_item' => round($item->getPrice(), 2)
                );
                if ($item->getProductType() == 'configurable') {
                    foreach ($item->getChildren() as $child) {
                        $itemData['selected_attributes'] = trim(array_pop(explode(',', $child->getName())));
                    }
                }
                $items[] = $itemData;
            }

            $data['items'][] = $items;

            return $data;
        }

        if ($this->_message) {
            return $this->_message;
        }
    }
}