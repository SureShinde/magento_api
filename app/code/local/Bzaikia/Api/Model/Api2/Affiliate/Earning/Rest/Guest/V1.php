<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Affiliate_Earning_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Affiliate_Earning
{
    protected $_customers;

    public function _retrieve()
    {
        if ($this->_isLogEnable()) {
            $this->_log($this->getRequest()->getParams());
        }
        $userId = $this->getRequest()->getParam('user_id');
        if ($this->_validateAuthorization($userId)) {
            $fromDate = $this->getRequest()->getParam('from_date');
            $toDate = $this->getRequest()->getParam('to_date');
            if ($userId && !$this->_getCustomer($userId)) return 'customer is not exist';

            $transactionCollection = $this->_getTransactionCollection($userId, $fromDate, $toDate);
            $result = array();
            $totalEarning = 0;

            foreach ($transactionCollection as $transaction) {
                if (empty($this->_customers[$transaction->getCustomerId()])) {
                    $customer = Mage::getModel('customer/customer')->load($transaction->getCustomerId());
                    $this->_customers[$transaction->getCustomerId()] = $customer;
                }
                $customer = $this->_customers[$transaction->getCustomerId()];
                $result[] = array(
                    'user_id' => $customer->getId(),
                    'user_id_external' => $customer->getData('user_id_external'),
                    'affiliate_id_external' => $customer->getData('affiliate_id_external'),
                    'earning_amount' => $transaction->getCommission(),
                    'timestamp' => $transaction->getCreatedTime(),
                    'orderID' => $transaction->getOrderId()
                );

                $totalEarning += $transaction->getCommission();
            }


            return array(
                'total' => $totalEarning,
                'earnings' => $result
            );
        }

        if ($this->_message) {
            return $this->_message;
        }
    }

    /**
     * @param $userId
     * @param $fromData
     * @param $toDate
     * @return object
     */
    protected function _getTransactionCollection($userId, $fromData, $toDate)
    {
        $transactionCollection = Mage::getModel('affiliateplus/transaction')->getCollection();
        $transactionCollection->addFieldToFilter('status', array('eq' => 1));
        if ($userId) {
            $transactionCollection->addFieldToFilter('account_email', array('eq' => $this->_getCustomer($userId)->getEmail()));
        }
        if ($fromData) {
            $transactionCollection->addFieldToFilter('created_time', array('gteq' => $fromData));
        }
        if ($toDate) {
            $transactionCollection->addFieldToFilter('created_time', array('lteq' => $toDate));
        }

        $transactionCollection->addFieldToFilter('store_id', array('eq' => 1));
        $transactionCollection->addFieldToFilter('transaction_is_deleted', array('eq' => 0));

        return $transactionCollection;
    }

}