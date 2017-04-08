<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Info_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Info
{
    const ERROR_MESSAGE_NO_QUOTE_ITEM_FOUND = 'no quote item found';

    public function _retrieveCollection()
    {
        if ($this->_isLogEnable()) {
            $this->_log($this->getRequest()->getParams());
        }
        $cartId = $this->getRequest()->getParam('cart_id');
        if (empty($cartId)) {
            return self::ERROR_MESSAGE_LACKING_PARAM;
        }

        $quoteCollection = Mage::getModel('sales/quote')->getCollection()
            ->addFieldToFilter('is_active', array('eq' => 1))
            ->addFieldToFilter('entity_id', array('eq' => $cartId));
        $quote = $quoteCollection->getFirstItem();
        if ($quote && $quote->getId()) {
            if ($this->_validateAuthorization($quote->getCustomerId())) {
                $result = array();
                foreach ($quote->getItemsCollection() as $item) {
                    if (!$item->getParentItemId()) {
                        $product = $item->getProduct();
                        $result[] = array(
                            'product_image' => $product->getImageUrl(),
                            'product_title' => $product->getName(),
                            'product_weight' => $product->getWeight(),
                            'product_flavour' => $product->getData('flavour'),
                            'brand_name' => $this->_getBrandName($product->getId()),
                            'quantity' => intval($item->getQty()),
                            'price' => round($item->getPrice(), 2)
                        );
                    }
                }
                return $result;
            } else {
                return $this->_message;
            }
        }
        return self::ERROR_MESSAGE_NO_QUOTE_ITEM_FOUND;

    }
}