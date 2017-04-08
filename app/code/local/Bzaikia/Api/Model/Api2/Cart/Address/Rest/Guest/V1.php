<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Cart_Address_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Cart_Address
{
    protected $_addressMapping;
    const DUMP_PHONE = '123-456-7890';
    const ERROR_ADDRESS_NOT_VALID = 'address data is not valid';

    protected function _init()
    {
        $this->_addressMapping = array(
            'Firstname' => 'firstname',
            'Lastname' => 'lastname',
            'Street' => array('address_line_one', 'address_line_two'),
            'Postcode' => 'postalcode',
            'City' => 'city',
            'CountryId' => 'country',
            'Telephone' => 'telephone'
        );
    }

    /**
     * @param array $filteredData
     */
    public function _create(array $filteredData)
    {
        if ($this->_isLogEnable()) {
            $this->_log($filteredData);
        }
        $this->setCustomerId($filteredData[self::PARAM_USER_ID]);
        $userId = $this->getCustomerId();

        if ($this->_validateAuthorization($userId) && $this->_validate($filteredData)) {
            if ($this->_validateAddressData($filteredData)) {
                if ($quote = $this->_getQuote($userId)) {
                    $this->_createAddress($quote, $filteredData);
                    $quote->collectTotals();
                    $quote->save();
                    $this->_updateBraintreeAddress();
                } else {
                    $this->getResponse()->setBody(self::ERROR_MESSAGE_LACKING_PARAM);
                    $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                }
            } else {
                $this->getResponse()->setBody(self::ERROR_ADDRESS_NOT_VALID);
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
            }
        }

        if ($this->_message) {
            $this->getResponse()->setBody($this->_message);
        }
    }

    /**
     * @param $quote
     * @param $data
     */
    protected function _createAddress($quote, $data)
    {
        $this->_init();
        $address = $quote->getBillingAddress();
        foreach ($this->_addressMapping as $key => $value) {
            if (is_array($value)) {
                $address->{'set' . $key}($data['address_line_one'] . "\n" . $data['address_line_two']);
            } else {
                $address->{'set' . $key}($data[$value]);
            }
        }
        if (!$address->getTelephone()) {
            $address->setTelephone(self::DUMP_PHONE);
        }

        // set email for newly created user
        if (!$address->getEmail() && $quote->getCustomerEmail()) {
            $address->setEmail($quote->getCustomerEmail());
        }
        $address->setSaveInAddressBook(1);

        $billing = clone $address;
        $billing->unsAddressId()->unsAddressType();

        $shipping = $quote->getShippingAddress();
        $shippingMethod = $shipping->getShippingMethod();
        $shipping->addData($billing->getData())
            ->setSameAsBilling(1)
            ->setSaveInAddressBook(1)
            ->setShippingMethod($shippingMethod)
            ->setCollectShippingRates(true);
    }

    /**
     * @return array
     */
    public function _retrieve()
    {
        if ($this->_isLogEnable()) {
            $this->_log($this->getRequest()->getParams());
        }
        $userId = $this->getRequest()->getParam(self::PARAM_USER_ID);
        $this->setCustomerId($userId);
        $this->_init();
        if ($this->_validateAuthorization($userId) && $this->_validate($this->getRequest()->getParams())) {
            $quote = $this->_getQuote($userId);
            $result = array();
            $address = $quote->getShippingAddress();
            foreach ($this->_addressMapping as $key => $value) {
                if (is_array($value) && $address->getStreet()) {
                    $street = $address->getStreet();
                    $result['address_line_one'] = array_shift($street);
                    $result['address_line_two'] = array_shift($street);
                } else {
                    $result[$value] = $address->{'get' . $key}();
                }
            }
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
            return $result;

        }
        if ($this->_message) {
            return $this->_message;
        }
        $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
    }

    /**
     * @param $data
     * @return bool
     */
    protected function _validateAddressData($data)
    {
        return !empty($data['firstname']) &&
        !empty($data['lastname']) &&
        !empty($data['address_line_one']) &&
        !empty($data['postalcode']) &&
        !empty($data['city']) &&
        !empty($data['country']);
    }

    /**
     * @param $order Mage_Sales_Model_Order
     * @param $nounce
     */
    protected function _updateBraintreeAddress()
    {
        Mage::getModel('gene_braintree/wrapper_braintree')->init();
        $model = Mage::getModel('gene_braintree/wrapper_braintree');

        if ($this->getCustomer()->getData('braintree_customer_id')) {
            Braintree_Customer::update(
                $this->getCustomer()->getData('braintree_customer_id'),
                [
                    'firstName' => $this->_getQuote($this->getCustomerId())->getBillingAddress()->getFirstname(),
                    'lastName' => $this->_getQuote($this->getCustomerId())->getBillingAddress()->getLastname(),
                    'phone' => $this->_getQuote($this->getCustomerId())->getBillingAddress()->getTelephone()
                ]
            );
        }
    }
}