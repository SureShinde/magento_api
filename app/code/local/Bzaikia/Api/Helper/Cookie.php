<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Helper_Cookie extends Magestore_Affiliateplus_Helper_Cookie
{
    public function getAffiliateInfo($code)
    {
        if (!is_null($this->_affiliateInfo))
            return $this->_affiliateInfo;
        $info = array();
        $storeId = Mage::app()->getStore()->getId();
        //hainh 22-07-2014
        if ($code) {
            $accountCode=$code;
            $account = Mage::getModel('affiliateplus/account')->setStoreId(Mage::app()->getStore()->getId())->loadByIdentifyCode($accountCode);
//                Changed By Adam (29/08/2016): check if allow the affiliate to get commission from his purchase
            if ($account && $account->getId() && $account->getStatus() == 1
                && (Mage::helper('affiliateplus/config')->allowAffiliateToGetCommissionFromHisPurchase($storeId) || Mage::helper('affiliateplus/account')->getAccount() && $account->getId() != Mage::helper('affiliateplus/account')->getAccount()->getId())) {
                $info[$accountCode] = array(
                    'index' => 1,
                    'code' => $accountCode,
                    'account' => $account,
                );
            }
            $infoObj = new Varien_Object(array(
                'info' => $info,
            ));
            $this->_affiliateInfo = $infoObj->getInfo();
            return $this->_affiliateInfo;
        }
//end edit
        // Check Life-Time sales commission
        if (Mage::helper('affiliateplus/config')->getCommissionConfig('life_time_sales')) {
            $tracksCollection = Mage::getResourceModel('affiliateplus/tracking_collection');
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            if ($customer && $customer->getId()) {
                $tracksCollection->getSelect()
                    ->where("customer_id = {$customer->getId()} OR customer_email = ?", $customer->getEmail());
            } else {
                /* hainh update 25-04-2014 */
                if (Mage::getSingleton('checkout/session')->hasQuote()) {
                    $quote = Mage::getSingleton('checkout/session')->getQuote();
                    $customerEmail = $quote->getCustomerEmail();
                } else {
                    $customerEmail = "";
                }
                $tracksCollection->addFieldToFilter('customer_email', $customerEmail);
                /* end update */
            }
            $track = $tracksCollection->getFirstItem();
            if ($track && $track->getId()) {
                $account = Mage::getModel('affiliateplus/account')
                    ->setStoreId(Mage::app()->getStore()->getId())
                    ->load($track->getAccountId());
                if($account && $account->getStatus() == 1){
                    $info[$account->getIdentifyCode()] = array(
                        'index' => 1,
                        'code' => $account->getIdentifyCode(),
                        'account' => $account,
                    );
                    $this->_affiliateInfo = $info;
                    return $this->_affiliateInfo;
                }
            }
        }

        $cookie = Mage::getSingleton('core/cookie');
        $map_index = $cookie->get('affiliateplus_map_index');
        $flag = false;

        for ($i = $map_index; $i > 0; $i--) {
            $accountCode = $cookie->get("affiliateplus_account_code_$i");
            $account = Mage::getModel('affiliateplus/account')->setStoreId(Mage::app()->getStore()->getId())->loadByIdentifyCode($accountCode);
//          Changed By Adam (29/08/2016): check if allow the affiliate to get commission from his purchase
            if ($account && $account->getStatus() == 1) {
                $info[$accountCode] = array(
                    'index' => $i,
                    'code' => $accountCode,
                    'account' => $account,
                );
                $flag = true;
            }
        }
//          Changed By Adam (29/08/2016): check if allow the affiliate to get commission from his purchase
        if(!$flag) {

            //      Changed By Adam (29/08/2016): check if allow the affiliate to get commission from his purchase
            if(Mage::helper('affiliateplus/config')->allowAffiliateToGetCommissionFromHisPurchase($storeId)){
                $account = Mage::getSingleton('affiliateplus/session')->getAccount();
                if($account && $account->getStatus() == 1) {
                    $info[$accountCode] = array(
                        'index' => 1,
                        'code' => $account->getIdentifyCode(),
                        'account' => $account,
                    );
                }
            }
        }
        $infoObj = new Varien_Object(array(
            'info' => $info,
        ));
        Mage::dispatchEvent('affiliateplus_get_affiliate_info', array(
            'cookie' => $cookie,
            'info_obj' => $infoObj,
        ));

        $this->_affiliateInfo = $infoObj->getInfo();
        return $this->_affiliateInfo;
    }
}