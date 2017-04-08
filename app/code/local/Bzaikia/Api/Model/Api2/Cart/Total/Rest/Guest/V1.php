<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Total_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Total
{
    public function _retrieve()
    {
        if ($this->_isLogEnable()) {
            $this->_log($this->getRequest()->getParams());
        }
        $userId = $this->getRequest()->getParam('user_id');
        $this->setCustomerId($userId);
        if ($this->_validateAuthorization($userId)) {
            if ($this->getCustomer()->getId() && $quote = $this->_getQuote($this->getCustomerId())) {
                $shippingAmount = $quote->getShippingAddress()->getShippingAmount();
                $result = array(
                    'subtotal' => round($quote->getSubtotal(), 2),
                    'shipping' => round($shippingAmount, 2),
                    'discount' => round($quote->getSubtotalWithDiscount() - $quote->getSubtotal(), 2),
                    'total' => round($quote->getGrandTotal() ,2)
                );

                return $result;
            }
        } else {
            return $this->_message;
        }

        return array();
    }
}