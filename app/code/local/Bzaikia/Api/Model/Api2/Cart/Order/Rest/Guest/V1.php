<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Order_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Order
{
    const SUCCESSFUL_MESSAGE = 'Order has been created';
    const ERROR_MESSAGE = 'Order is not created, something is missing';
    const PARAM_PAYMENT_NONCE = 'payment_method_nonce';

    public function _create(array $filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $this->setCustomerId($filteredData[self::PARAM_USER_ID]);
        $quote = $this->_getQuote($this->getCustomerId());
        if ($this->_validateAuthorization($filteredData[self::PARAM_USER_ID]) && $this->_validate($filteredData)) {
            $this->_importPaymentData($quote, $filteredData);
            $this->getOnepage()->setQuote($quote);
            $this->_prepareCustomerQuote($quote, $this->getCustomer());
            /**
             * start integrate with magestore affiliate module
             */
            if ($affId = $this->getCustomer()->getData('affiliate_id_external')) {
                $affCustomer = Mage::getModel('customer/customer')->getCollection();
                $affCustomer->addAttributeToFilter('user_id_external', array('eq' => $affId));
                if ($affCustomer->getFirstItem() && $affCustomer->getFirstItem()->getId()) {
                    $account = Mage::getModel('affiliateplus/account')->loadByCustomerId($affCustomer->getFirstItem()->getId());
                    if ($account->getIdentifyCode()) {
                        Mage::register('aff_code', $account->getIdentifyCode());
                    }
                }
            }
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();
            $order = $service->getOrder();
            if ($order) {
                $quote->setIsActive(0)->save();
                Mage::dispatchEvent('checkout_type_onepage_save_order_after',
                    array('order' => $order, 'quote' => $quote));
                $this->getResponse()->setBody(self::SUCCESSFUL_MESSAGE);
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
            } else {
                $this->getResponse()->setBody(self::ERROR_MESSAGE);
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
            }
        }

        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    /**
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function getOnepage()
    {
        return Mage::getSingleton('checkout/type_onepage');
    }

    protected function _prepareCustomerQuote($quote, $customer)
    {
        $billing = $quote->getBillingAddress();
        $shipping = $quote->isVirtual() ? null : $quote->getShippingAddress();

        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }
        if ($shipping && !$shipping->getSameAsBilling() &&
            (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())
        ) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }
        if ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        } else if (isset($customerBilling) && !$customer->getDefaultShipping()) {
            $customerBilling->setIsDefaultShipping(true);
        }
        $quote->setCustomer($customer);
    }

    /**
     * @param $quote
     * @param $data
     */
    protected function _importPaymentData($quote, $data)
    {
        $data = array_merge($data,
            array(
                'method' => $quote->getPayment()->getMethod(),
                'device_data'
            )
        );
        if ($data) {
            $data['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
                | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
                | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
                | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
                | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;
            $quote->getPayment()->importData($data);
        }
    }

    /**
     * @param $params
     * @return bool
     */
    protected function _validate($params)
    {
        if (empty($params[self::PARAM_PAYMENT_NONCE])) {
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
            $this->getResponse()->setBody(self::ERROR_MESSAGE_LACKING_PARAM);
            return false;
        }

        return parent::_validate($params); // TODO: Change the autogenerated stub
    }
}