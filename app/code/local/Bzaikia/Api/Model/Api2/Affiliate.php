<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Affiliate extends Bzaikia_Api_Model_Resource
{
    protected $_customer;

    /**
     * @param $djangoId
     * @return mixed
     */
    protected function _getCustomer($djangoId)
    {
        if (!isset($this->_customer)) {
            $customerCollection = Mage::getModel('customer/customer')->getCollection();
            $customerCollection->addAttributeToFilter('user_id_external', array('eq' => $djangoId));

            if ($customerCollection->getFirstItem() && $customerCollection->getFirstItem()->getId()) {
                $this->_customer = $customerCollection->getFirstItem();
            }
        }
        return $this->_customer;
    }

    /**
     * @param $customer int
     * @return mixed
     */
    protected function _getAffiliateAccountByCustomerId($customer)
    {
        $affiliateCollection = Mage::getModel('affiliateplus/account')->getCollection();
        $affiliateCollection->addFieldToFilter('customer_id', array('eq' => $customer));
        $affiliateAcc = $affiliateCollection->getFirstItem();

        return $affiliateAcc;
    }

    protected function _validate($data) {
        $customer = Mage::getModel('customer/customer')->load($data[self::PARAM_USER_ID]);
        if (!$customer->getId()) {
            $this->_message = 'user does not exist';

            return false;
        }

        if (!$customer->getData('is_affiliate')) {
            $this->_message = 'user is not affiliate';

            return false;
        }

        return true;
    }
}