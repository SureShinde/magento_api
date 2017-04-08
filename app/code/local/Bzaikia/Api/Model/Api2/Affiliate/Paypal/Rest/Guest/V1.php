<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Affiliate_Paypal_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Affiliate_Paypal
{
    const PARAM_PAYPAL_EMAIL = 'paypal_email';
    const ERROR_MESSAGE_AFFILIATE_NOT_EXIST = 'the supplied user id does not associate to any affiliate account';

    protected $_affiliate_account = null;

    public function _retrieve()
    {
        $userId = $this->getRequest()->getParam(self::PARAM_USER_ID);
        if ($this->_validateAuthorization($userId) && parent::_validate($this->getRequest()->getParams())) {
            $affiliateAcc = $this->_getAffiliateAccountByCustomerId($userId);

            if ($affiliateAcc && $affiliateAcc->getId()) {
                return $affiliateAcc->getData('paypal_email');
            }

            return null;
        }

        if ($this->_message) {
            return $this->_message;
        }
    }

    public function _create(array $filteredData)
    {
        if ($this->_validateAuthorization($filteredData[self::PARAM_USER_ID]) && $this->_validate($filteredData)) {
            $affiliateAcc = $this->_getAffiliateAccountByCustomerId($filteredData[self::PARAM_USER_ID]);
            if ($affiliateAcc && $affiliateAcc->getId()) {
                $affiliateAcc->setData('paypal_email', $filteredData[self::PARAM_PAYPAL_EMAIL])->save();
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
            } else {
                $this->_message = self::ERROR_MESSAGE_AFFILIATE_NOT_EXIST;
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
            }
        }

        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    /**
     * @param $data
     * @return bool
     */
    protected function _validate($data)
    {
        $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
        if (empty($data[self::PARAM_USER_ID]) || empty($data[self::PARAM_PAYPAL_EMAIL])) {
            $this->_message = self::ERROR_MESSAGE_LACKING_PARAM;

            return false;
        }

        return parent::_validate($data);
    }
}