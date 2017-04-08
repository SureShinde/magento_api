<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_User_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_User
{
    const ERROR_CAN_NOT_CREATE_QUOTE = 'can not create quote for the user';
    const DUMP_PHONE = '123-456-7890';

    public function _create(array $filteredData)
    {
        $hashData = $filteredData['payload'];
        $data = $this->_getParam($hashData);
        $customer = $this->_getCustomerByEmail($data->user_email);
        $dataArr = array(
            'user_id_external' => $data->user_id_external,
            'is_affiliate' => $data->is_affiliate,
            'affiliate_id_external' => $data->affiliate_id_external,
            'partner' => $data->partner
        );
        if (!$data->is_affiliate) {
            $customerCollection = Mage::getModel('customer/customer')->getCollection();
            $customerCollection->addAttributeToFilter('user_id_external', array('eq' => $data->affiliate_id_external));

            $affiliater = $customerCollection->getFirstItem();

            if (!$affiliater->getId()) {
                $result = array(
                    'error' => 'Trainer with id ' . $data->affiliate_id_external . ' is not exist'
                );

                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                $this->getResponse()->setBody(json_encode($result));
                return;
            }
        }

        foreach ($dataArr as $key => $value) {
            $customer->setData($key, $value);
        }
        try {
            $customer->setData('braintree_customer_id', $this->_createBraintreeCustomerId($customer->getEmail()));
            $customer->save();
            if ($data->is_affiliate) {
                $account = Mage::getModel('affiliateplus/account')->loadByCustomerId($customer->getId());
                if (!$account->getId()) {
                    $successMessage = Mage::helper('affiliateplus/account')->createAffiliateAccount($account->getEmail(), $account->getEmail(), $customer, 0, '', '', null, null, '');
                }
            }
            $customerData = $customer->getData();
            unset($customerData['braintree_customer_id']);
            $result = array(
                'user_id' => $customer->getId(),
                'user' => $customerData
            );
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);

            if (!$this->_createQuote($customer->getId())) {
                $this->getResponse()->setBody(self::ERROR_CAN_NOT_CREATE_QUOTE);
            }
        } catch (Exception $e) {
            $result = array(
                'error' => $e->getMessage()
            );

            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
        }
        $this->getResponse()->setBody(json_encode($result));
    }

    /**
     * @param $email
     * @return mixed
     */
    protected function _getCustomerByEmail($email)
    {
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId(Bzaikia_Api_Helper_Data::CURRENT_STORE)
            ->loadByEmail($email);
        if (!$customer->getId()) {
            $customer->setEmail($email);
        }
        return $customer;
    }

    /**
     * @param $userId
     * @return bool
     */
    protected function _createQuote($userId)
    {
        $quote = Mage::getModel('sales/quote')
            ->setStoreId(1)
            ->setCustomerId($userId);
        $quote->setIsCheckoutCart(true);
        Mage::dispatchEvent('checkout_quote_init', array('quote' => $quote));
        try {
            $quote->save();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @param $email
     * @return string
     */
    protected function _createBraintreeCustomerId($email)
    {
        Mage::getModel('gene_braintree/wrapper_braintree')->init();
        $model = Mage::getModel('gene_braintree/wrapper_braintree');
        $result = Braintree_Customer::create([
            'firstName' => 'Customer First Name',
            'lastName' => 'Customer Last Name',
            'email' => $email,
            'phone' => self::DUMP_PHONE,
            'fax' => self::DUMP_PHONE
        ]);

        if ($result->success) {
            return $result->customer->id;
        }

        return '';
    }
}