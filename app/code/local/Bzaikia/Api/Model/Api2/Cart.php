<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart extends Bzaikia_Api_Model_Resource
{
    protected $_customerId;
    protected $_quote;
    const ERROR_QUOTE_NOT_FOUND = 'can not found active quote for current user';
    const CURRENT_STORE = 1;
    const SERVER_ERROR_CODE = 500;
    const CLIENT_ERROR_CODE = 400;
    const SUCCESSFUL_CODE = 200;
    const EXIST_QUOTE_ERROR = 'there is an active quote for the customer, you can not create multiple active quote for same customer';

    /**
     * @param $userId
     * @return Mage_Sales_Model_Quote | bool
     */
    protected function _getQuote($userId)
    {
        if (empty($this->_quote)) {
            $quote = Mage::getModel('sales/quote')->loadByCustomer($userId);
            if ($quote->getId()) {
                $this->_quote = $quote;
            } else {
                $quote = Mage::getModel('sales/quote')
                    ->setStoreId(self::CURRENT_STORE)
                    ->setCustomerId($userId);
                $quote->setIsCheckoutCart(true);
                Mage::dispatchEvent('checkout_quote_init', array('quote' => $quote));

                try {
                    $quote->save();
                    if (!$quote->getId()) {
                        Mage::helper('bzaikia_api/log')->log('can not instance quote');
                        $this->getResponse()->setHttpResponseCode(self::SERVER_ERROR_CODE);
                    }
                    $this->_setQuoteAddress($quote);
                    $this->_quote = $quote;
                } catch (Exception $e) {
                    Mage::helper('bzaikia_api/log')->log($e->getMessage());
                    $result['status'] = self::SERVER_ERROR_CODE;
                    return false;
                }
            }
        }
        return $this->_quote;
    }

    /**
     * @param $id int
     */
    public function setCustomerId($id)
    {
        $this->_customerId = $id;
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getCustomerId()
    {
        if ($this->getCustomer())
            return $this->getCustomer()->getId();
        return 0;
    }

    /**
     * @param $id
     * @return Mage_Customer_Model_Customer | int
     */
    public function getCustomer()
    {
        $model = Mage::getModel('customer/customer')->load($this->_customerId);
        if ($model->getId()) {
            return $model;
        }
        return 0;
    }

    /**
     * @param $productId
     * @return string
     */
    protected function _getBrandName($productId)
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

    /**
     * @param $quote Mage_Sales_Model_Quote
     */
    protected function _setQuoteAddress($quote)
    {
        $customer = $this->getCustomer();

        if ($customer->getDefaultBillingAddress() && $customer->getDefaultShippingAddress()) {
            $billing = $quote->getBillingAddress();
            $billing->addData($customer->getDefaultBillingAddress()->getData())->save();

            $shipping = $quote->getShippingAddress();
            $shipping->addData($customer->getDefaultShippingAddress()->getData())->setCollectShippingRates(true);

            $quote->collectTotals();
            $quote->save();
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

    /**
     * @param $params
     * @return bool
     */
    protected function _validate($params)
    {
        $quote = $this->_getQuote($params[self::PARAM_USER_ID]);

        if (!$quote) {
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
            $this->_message = self::ERROR_QUOTE_NOT_FOUND;
            return false;
        }

        return true;
    }

    /**
     * @param $userId
     * @return bool
     */
    public function _validateAuthorization($userId)
    {
        return parent::_validateAuthorization($userId, $this->_getQuote($userId));
    }
}