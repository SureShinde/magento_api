<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Orders_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Orders
{
    public function _retrieveCollection()
    {
        if ($this->_validateAuthorization()) {
            $timestamp = $this->getRequest()->getParam('timestamp');

            if (empty($timestamp)) {
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                return 'timestamp parameter is missing';
            }

            $orderCollection = Mage::getModel('sales/order')->getCollection();
            $orderCollection->addFieldToFilter('updated_at', array('gt' => date('Y/m/d', trim($timestamp))));

            $result = array();
            /**
             * @var $order Mage_Sales_Model_Order
             */
            foreach ($orderCollection as $order) {
                $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
                $item = array(
                    'magento_order_id' => intval($order->getId()),
                    'order_status' => $order->getStatus(),
                    'external_user_id' => intval($customer->getData('user_id_external')),
                    'external_bundle_id' => $order->getData('external_bundle_id'),
                    'external_affiliate_id' => intval($customer->getData('affiliate_id_external')),
                    'shipping_and_handling' => $order->getShippingMethod(),
                    'total_price' => round($order->getGrandTotal(), 2),
                    'products' => array()
                );
                /**
                 * @var $orderItem Mage_Sales_Model_Order_Item
                 */
                foreach ($order->getAllVisibleItems() as $orderItem) {
                    if ($orderItem->getRowInvoiced() > 0) {
                        $pricePaid = $orderItem->getRowInvoiced() - $orderItem->getDiscountAmount();
                    } else {
                        $pricePaid = 0;
                    }

                    $item['products'][] = array(
                        'sku' => $orderItem->getSku(),
                        'quantity' => intval($orderItem->getQtyOrdered()),
                        'price_paid' => round($pricePaid, 2)
                    );
                }
                $result[] = $item;
            }

            return $result;
        }

        if ($this->_message) {
            return $this->_message;
        }
    }

    /**
     * @return bool
     */
    public function _validateAuthorization()
    {
        $authorizationData = $_SERVER['HTTP_AUTHORIZATION'];
        $authorizationDataArr = explode(' ', $authorizationData);
        try {
            if (array_shift($authorizationDataArr) == 'Bearer') {
                $data = Mage::helper('bzaikia_jwt')->decode(array_shift($authorizationDataArr));
                if ($data->is_admin) {
                    $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
                    return true;
                }
                return false;
            } else {
                $this->_message = self::ERROR_MISSING_AUTHORIZATION_HEADER;
            }
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::AUTHORIZATION_VALIDATION_FAIL_CODE);
            return false;
        } catch (Exception $e) {
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::AUTHORIZATION_VALIDATION_FAIL_CODE);
            $this->_message = self::ERROR_EXCEPTION_FOUND_WHEN_DECODING . $e->getMessage();
            return false;
        }
    }
}