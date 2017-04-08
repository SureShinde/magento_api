<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Promo_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Promo
{
    const DISCOUNT_CODE = 'code';
    const SERVER_ERROR_CODE = '412';
    const ERROR_COUPON_CODE_NOT_AVAILABLE = 'your discount code is not available';

    public function _create(array $filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $userId = $filteredData[self::PARAM_USER_ID];
        $code = $filteredData[self::DISCOUNT_CODE];
        $this->setCustomerId($userId);
        if ($this->_validateAuthorization($userId) && $this->_validate($filteredData)) {
            $quote = $this->_getQuote($userId);
            try {
                $codeLength = strlen($code);
                $isCodeLengthValid = $codeLength && $codeLength <= Mage_Checkout_Helper_Cart::COUPON_CODE_MAX_LENGTH;
                $quote->getShippingAddress()->setCollectShippingRates(true);
                $quote->setCouponCode($code)
                    ->collectTotals()
                    ->save();

                if ($codeLength) {
                    if (!$isCodeLengthValid || $code != $quote->getCouponCode()) {
                        $this->_message = self::ERROR_COUPON_CODE_NOT_AVAILABLE;
                        $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
                    }
                }

            } catch (Exception $e) {
                $this->getResponse()->setBody($e->getMessage());
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
            }
            $discountPercent = round((($quote->getSubtotal() - $quote->getSubtotalWithDiscount()) * 100 / $quote->getSubtotal()), 1);
            $result = array('discount' => $discountPercent);
            $this->_message = json_encode($result);
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
        }

        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    /**
     * @param $filteredData
     * @return bool
     */
    protected function _validate($filteredData)
    {
        if (empty($filteredData[self::PARAM_USER_ID]) || empty($filteredData[self::DISCOUNT_CODE])) {
            $this->_message = self::ERROR_MESSAGE_LACKING_PARAM;
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);

            return false;
        }

        return parent::_validate($filteredData);
    }
}
