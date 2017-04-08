<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Affiliate_Sold_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Affiliate_Sold
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

            $transactionCollection = $this->_getSoldProductCollection($userId, $fromDate, $toDate);
            $result = array();
            $total = 0;

            foreach ($transactionCollection as $transaction) {
                if (empty($this->_customers[$transaction['customer_id']])) {
                    $customer = Mage::getModel('customer/customer')->load($transaction['customer_id']);
                    $this->_customers[$transaction['customer_id']] = $customer;
                }
                $customer = $this->_customers[$transaction['customer_id']];
                $result[] = array(
                    'user_id' => $customer->getId(),
                    'user_id_external' => $customer->getData('user_id_external'),
                    'affiliate_id_external' => $customer->getData('affiliate_id_external'),
                    'product_name' => $transaction['name'],
                    'product_sku' => $transaction['sku'],
                    'product_total' => $transaction['qty_ordered'],
                    'timestamp' => $transaction['created_time'],
                    'orderID' => $transaction['order_id']
                );
                $total += $transaction['affiliateplus_commission'];
            }
            return array(
                'total' => $total,
                'sales' => $result
            );
        } else {
            return $this->_message;
        }


    }

    /**
     * @param $userId
     * @param $fromDate
     * @param $toDate
     * @return array
     */
    protected function _getSoldProductCollection($userId, $fromDate, $toDate)
    {
        $transactionTable = Mage::getSingleton('core/resource')->getTableName('affiliateplus/transaction');
        $salesItemTable = Mage::getSingleton('core/resource')->getTableName('sales/order_item');

        $transactionSql = $this->_getWriteAdapter()->select()
            ->from($transactionTable)
            ->where('affiliateplus_transaction.status = 1')
            ->where('affiliateplus_transaction.store_id = 1')
            ->where('affiliateplus_transaction.transaction_is_deleted = 0');

        if ($fromDate) {
            $transactionSql->where('affiliateplus_transaction.created_time >= ?', $fromDate);
        }

        if ($toDate) {
            $transactionSql->where('affiliateplus_transaction.created_time <= ?', $toDate);
        }
        if ($userId) {
            $transactionSql->where('account_email = ?', $this->_getCustomer($userId)->getEmail());
        }

        $salesItemTableSelect = $this->_getWriteAdapter()->select()->from($salesItemTable)->where('parent_item_id is null');

        $transactionSql
            ->join(array('t' => new Zend_Db_Expr('(' . $salesItemTableSelect->__toString() . ')')), 'affiliateplus_transaction.order_id = t.order_id');


        return $this->_getWriteAdapter()->fetchAll($transactionSql);
    }

    /**
     * Retrieve connection for write data
     *
     * @return Varien_Db_Adapter_Interface
     */
    protected function _getWriteAdapter()
    {
        $resource = Mage::getSingleton('core/resource');
        /**
         * @var $writeAdapter Magento_Db_Adapter_Pdo_Mysql
         */
        return $resource->getConnection('core_write');
    }
}