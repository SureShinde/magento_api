<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Shipping_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Shipping
{
    const PARAM_SHIPPING_METHOD = 'shipping_method';
    const MISSING_SHIPPING_METHOD = 'missing shipping method';
    const ERROR_UPDATE_SHIPPING_METHOD = 'the supplied shipping method is not available';
    const NOTICE_UPDATE_SHIPPING_METHOD_WORK = 'Applied shipping method successfully';

    /**
     * @return array
     */
    public function _retrieveCollection()
    {
        if ($this->_isLogEnable()) {
            $this->_log($this->getRequest()->getParams());
        }
        $userId = $this->getRequest()->getParam('user_id');
        $this->setCustomerId($userId);
        if ($this->_validateAuthorization($userId)) {
            $quote = $this->_getQuote($userId);
            $result = array();
            $shipping = $quote->getShippingAddress()->getGroupedAllShippingRates();
            foreach ($shipping as $code => $rates) {
                foreach ($rates as $rate) {
                    $result[] = array(
                        'shipping_code' => $rate->getCode(),
                        'name' => $rate->getCarrierTitle(),
                        'price' => round($rate->getPrice(), 2)
                    );
                }
            }
        } else {
            return $this->_message;
        }

        return $result;
    }

    /**
     * @param array $filteredData
     */
    public function _create(array $filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $this->setCustomerId($filteredData['user_id']);
        $userId = $this->getCustomerId();
        $shippingMethod = $filteredData['shipping_method'];
        if ($this->_validateAuthorization($userId) && $this->validate($filteredData)) {
            $quote = $this->_getQuote($userId);
            $rate = $quote->getShippingAddress()->getShippingRateByCode($shippingMethod);
            if (!$rate) {
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
                $this->getResponse()->setBody(self::ERROR_UPDATE_SHIPPING_METHOD);
                return;
            }
            $quote->getShippingAddress()
                ->setShippingMethod($shippingMethod);
            $quote->collectTotals()->save();
            $this->getResponse()->setBody(self::NOTICE_UPDATE_SHIPPING_METHOD_WORK);
        }
        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    /**
     * @param $filteredData
     * @return bool
     */
    protected function validate($filteredData)
    {
        // validate data existence
        if (empty($filteredData[self::PARAM_USER_ID]) || empty($filteredData[self::PARAM_SHIPPING_METHOD])) {
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
            $this->getResponse()->setBody(self::ERROR_MESSAGE_LACKING_PARAM);
            return false;
        }

        return parent::_validate($filteredData);
    }
}