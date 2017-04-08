<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Affiliate_Balance_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Affiliate_Balance
{
    public function _retrieve()
    {
        $userId = $this->getRequest()->getParam(self::PARAM_USER_ID);
        if ($this->_validateAuthorization($userId) && $this->_validate($this->getRequest()->getParams())) {
            $affiliateAcc = $this->_getAffiliateAccountByCustomerId($userId);
            if ($affiliateAcc && $affiliateAcc->getId()) {

                $result = array(
                    'user_id' => intval($userId),
                    'name' => $affiliateAcc->getName(),
                    'email' => $affiliateAcc->getEmail(),
                    'balance'=> round($affiliateAcc->getBalance(), 2),
                    'commission_paid'=> round($affiliateAcc->getData('total_commission_received'), 2),
                    'total_paid' => round($affiliateAcc->getData('total_paid'), 2)
                );

                return $result;
            }

            return '';
        }

        if ($this->_message) {
            return $this->_message;
        }
    }
}