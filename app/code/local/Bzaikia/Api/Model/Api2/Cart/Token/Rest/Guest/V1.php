<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Token_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Token
{
    public function _retrieve()
    {
        if ($this->_isLogEnable()) {
            $this->_log($this->getRequest()->getParams());
        }
        $userId = $this->getRequest()->getParam('user_id');
        $this->setCustomerId($userId);
        $test = $this->getRequest()->getParam('test');
        if ($this->_validateAuthorization($userId)) {
            $key = Mage::getSingleton('bzaikia_api/braintree')->init()->generateToken($this->_getCustomerBraintreeId($userId), $test);
            return $key;
        }

        return $this->_message;
    }

    /**
     * @param $userId
     * @return mixed
     */
    public function _getCustomerBraintreeId($userId)
    {
        $customer = Mage::getModel('customer/customer')->load($userId);
        if ($customer->getId())
            return $customer->getData('braintree_customer_id');
        return '';
    }
}