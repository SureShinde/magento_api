<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Payment_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Payment
{
    const PARAM_PAYMENT_METHOD = 'payment_method';

    public function _create(array $filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $this->setCustomerId($filteredData[self::PARAM_USER_ID]);
        $userId = $this->getCustomerId();
        $code = $filteredData[self::PARAM_PAYMENT_METHOD];

        if ($this->_validateAuthorization($filteredData[self::PARAM_USER_ID]) && $this->_validate($filteredData)) {
            try {
                $quote = $this->_getQuote($userId);
                if ($quote->isVirtual()) {
                    $quote->getBillingAddress()->setPaymentMethod(isset($code) ? $code : null);
                } else {
                    $quote->getShippingAddress()->setPaymentMethod(isset($code) ? $code : null);
                }

                // shipping totals may be affected by payment method
                if (!$quote->isVirtual() && $quote->getShippingAddress()) {
                    $quote->getShippingAddress()->setCollectShippingRates(true);
                }
                $data['method'] = $code;
                $data['submit_after_payment'] = 1;
                $data['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                    | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                    | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                    | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;

                $payment = $quote->getPayment();
                $payment->importData($data);

                $quote->save();
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
            } catch (Exception $e) {
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
                $this->getResponse()->setBody($e->getMessage());
            }
        }

        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    /**
     * @param $params
     * @return bool
     */
    protected function _validate($params)
    {
        if (empty($params[self::PARAM_USER_ID]) || empty($params[self::PARAM_PAYMENT_METHOD])) {
            $this->getResponse()->setBody(self::ERROR_MESSAGE_LACKING_PARAM);
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);

            return false;
        }

        return parent::_validate($params);
    }
}