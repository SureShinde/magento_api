<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Product_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Product
{
    protected $_product;
    const PARAM_QUOTE_ITEM_ID = 'quote_item_id';
    const PARAM_QTY = 'qty';
    const PARAM_SKU = 'sku';
    const ERROR_QUOTE_ITEM_NOT_AVAILABLE = 'the supply quote item is not associate to current quote';

    /**
     * @param array $filteredData
     * @return string|void
     */
    public function _create($filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $products = $filteredData['products'];
        if (empty($products)) {
            $products = array(
                array(
                    'sku' => $filteredData[self::PARAM_SKU],
                    'qty' => $filteredData[self::PARAM_QTY]
                )
            );
        }

        $this->setCustomerId($filteredData[self::PARAM_USER_ID]);
        $userId = $this->getCustomerId();
        $quote = $this->_getQuote($userId);
        if (!empty($filteredData['external_bundle_id'])) {
            $quote->setData('external_bundle_id', $filteredData['external_bundle_id']);
        }
        $cart = Mage::getSingleton('bzaikia_api/cart');
        $cart->setQuote($quote);

        if ($this->_validateAuthorization($userId)) {
            $result = array('added_products' => array());
            $removeQuoteItem = array();
            foreach ($products as $product) {
                $sku = $product['sku'];
                $filteredData['sku'] = $sku;
                $filteredData['qty'] = $product['qty'];

                $productId = Mage::getModel("catalog/product")->getIdBySku($sku);

                if ($this->_validate($filteredData, 'create')) {
                    try {
                        $product = $this->_getProduct($productId, $sku);
                        $quoteItem = $this->_addProduct($product, $filteredData);
                        if ($quoteItem->getDelete()) {
                            $removeQuoteItem[] = $product->getSku();
                            continue;
                        }
                        $addQty = intval($quoteItem->getQty() - Mage::registry('old_qty'));
                        if ($addQty) {
                            $result['added_products'][] = array('sku' => $product->getsku(), 'quantity' => $addQty);
                        }
                        $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);

                    } catch (Exception $e) {
                        $this->_message .= $e->getMessage() . "\n";
                        $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                    }
                }
            }

            if ($this->_message) {
                $result['message'] = $this->_message;
            }
            $this->getResponse()->setBody(json_encode($result));
            $cart->save();

            foreach ($quote->getAllVisibleItems() as $item) {
                if (in_array($item->getProduct()->getSku(), $removeQuoteItem)) {
                    $item->delete();
                }
            }
            return;
        }


        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    /**
     * @param $product
     * @param $data
     */
    protected function _addProduct($product, $data)
    {
        $cart = Mage::getSingleton('bzaikia_api/cart');
        $params = array(
            'product' => $product,
            'qty' => $data[self::PARAM_QTY],
            'super_attribute' => $data['super_attribute']
        );
        $result = $cart->addProduct($product, $params);


        if (is_string($result)) {
            Mage::throwException($result);
        }

        return $result;
    }

    public function _update($filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $filteredData = $this->getRequest()->getBodyParams();
        $this->setCustomerId($filteredData[self::PARAM_USER_ID]);
        $userId = $this->getCustomerId();
        $qty = $filteredData[self::PARAM_QTY];
        $quoteItem = $filteredData[self::PARAM_QUOTE_ITEM_ID];

        $quote = $this->_getQuote($userId);
        if ($this->_validateAuthorization($userId)
            && $this->_validate($filteredData, 'update')
            && $this->_validateQuoteItem($quote, $quoteItem)
        ) {
            try {
                if (empty($qty) || empty($quoteItem)) {
                    throw new Exception(self::ERROR_MESSAGE_LACKING_PARAM);
                }
                $cart = Mage::getSingleton('bzaikia_api/cart');
                $cart->setQuote($quote);
                $data = array($quoteItem => array('qty' => $qty));
                $cartData = $cart->suggestItemsQty($data);
                $cart->updateItems($cartData)
                    ->save();

                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
            } catch (Exception $e) {
                $this->getResponse()->setBody(json_encode(array('message' => $e->getMessage())));
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
                return;
            }
        }

        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    public function _delete()
    {
        if ($this->_isLogEnable()) {
            $this->_log($this->getRequest()->getParams());
        }
        $filteredData = $this->getRequest()->getParams();
        $this->setCustomerId($filteredData[self::PARAM_USER_ID]);
        $userId = $this->getCustomerId();
        $quoteItem = $filteredData[self::PARAM_QUOTE_ITEM_ID];

        $quote = $this->_getQuote($userId);
        if ($this->_validateAuthorization($userId)
            && $this->_validate($filteredData, 'delete')
            && $this->_validateQuoteItem($quote, $quoteItem)
        ) {
            try {
                $quote->removeItem($quoteItem);
                $cart = Mage::getSingleton('bzaikia_api/cart');
                $cart->setQuote($quote);
                $cart->save();
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
            } catch (Exception $e) {
                $this->_message = $e->getMessage();
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
            }
        }

        if ($this->_message) {
            return $this->_message;
        }
    }

    /**
     * Get product object based on requested product information
     *
     * @param   mixed $productInfo
     * @return  Mage_Catalog_Model_Product
     */
    protected function _getProduct($productInfo, $sku)
    {
        $product = null;
        if ($productInfo instanceof Mage_Catalog_Model_Product) {
            $product = $productInfo;
        } elseif (is_int($productInfo) || is_string($productInfo)) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($productInfo);
        }
        $currentWebsiteId = Mage::app()->getStore()->getWebsiteId();
        if (!$product
            || !$product->getId()
            || !is_array($product->getWebsiteIds())
            || !in_array($currentWebsiteId, $product->getWebsiteIds())
        ) {
            Mage::throwException(Mage::helper('checkout')->__('The product with sku ' . $sku . ' does not exist.'));
        }

        return $product;
    }

    /**
     * @param $filteredData
     * @param $action
     * @return bool
     */
    protected function _validate($filteredData, $action)
    {
        if (empty($filteredData[self::PARAM_USER_ID])) {
            $this->getResponse()->setBody(self::ERROR_MESSAGE_LACKING_PARAM);
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
            return false;
        }

        if ($action == 'update' || $action == 'delete' || $action == 'create') {
            if ($action != 'create' && empty($filteredData[self::PARAM_QUOTE_ITEM_ID])) {
                $this->_message = self::ERROR_MESSAGE_LACKING_PARAM;
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                return false;
            }

            if ($action != 'delete' && empty($filteredData[self::PARAM_QTY])) {
                $this->_message = self::ERROR_MESSAGE_LACKING_PARAM;
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                return false;
            }

            if ($action == 'create' && empty($filteredData[self::PARAM_SKU])) {
                $this->_message = self::ERROR_MESSAGE_LACKING_PARAM;
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                return false;
            }
        }

        return parent::_validate($filteredData);
    }

    /**
     * @param $quote Mage_Sales_Model_Quote
     * @param $quoteItem int
     * @return bool
     */
    protected function _validateQuoteItem($quote, $quoteItem)
    {
        foreach ($quote->getAllVisibleItems() as $item) {
            if ($item->getId() == $quoteItem) {
                return true;
            }
        }

        $this->getResponse()->setBody(self::ERROR_QUOTE_ITEM_NOT_AVAILABLE);
        $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
        return false;
    }
}