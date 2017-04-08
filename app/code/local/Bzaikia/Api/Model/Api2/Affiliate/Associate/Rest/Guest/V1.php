<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Affiliate_Associate_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Affiliate_Associate
{
    public function _create(array $filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $userIdExternal = $filteredData['user_id_external'];
        $affiliateIdExternal = $filteredData['affiliate_id_external'];
        $customer = $this->_getCustomer($userIdExternal);
        if ($this->_validateAuthorization($customer->getId())) {
            if (!$this->_validate($affiliateIdExternal)) {
                $result = array(
                    'error' => 'the supplied affiliate_id_external is not associated to any of magento customer'
                );
                $this->getResponse()->setBody(json_encode($result));
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                return;
            }


            if ($customer && $customer->getId()) {
                $customer->setData('affiliate_id_external', $affiliateIdExternal);
                $customer->save();
            }
        }

        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    /**
     * @param $affiliateIdExternal int
     * @return bool
     */
    protected function _validate($affiliateIdExternal)
    {
        $customerCollection = Mage::getModel('customer/customer')->getCollection();
        $customerCollection->addAttributeToFilter('affiliate_id_external', array('eq' => $affiliateIdExternal));

        if ($customerCollection->getFirstItem() && $customerCollection->getFirstItem()->getId()) {
            return true;
        }

        return false;
    }
}