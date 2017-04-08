<?php

/**
 * Author: Rowan Burgess
 */
abstract class Bzaikia_Api_Model_Resource extends Mage_Api2_Model_Resource
{
    const PARAM_USER_ID = 'user_id';

    const ERROR_MESSAGE_LACKING_PARAM = 'some of parameters is missing, please make sure you send correct input to the API';
    const ERROR_MISSING_USER_INFO = 'Please supply user id in the authorization jwt string';
    const ERROR_USER_NOT_AUTHORIZE = 'the supplied user id does not have access to this field';
    const ERROR_MISSING_AUTHORIZATION_HEADER = 'the authorization header is missing or not in correct format: Bearer xxxxx';
    const ERROR_EXCEPTION_FOUND_WHEN_DECODING = 'error found when decode the authorization header: ';
    const ERROR_DATE_RANGE_MISSING = 'please supply date range';
    const LOG_FILE = 'api_log.log';

    protected $_message  = '';

    /**
     * Dispatch
     * To implement the functionality, you must create a method in the parent one.
     *
     * Action type is defined in api2.xml in the routes section and depends on entity (single object)
     * or collection (several objects).
     *
     * HTTP_MULTI_STATUS is used for several status codes in the response
     */
    public function dispatch()
    {
        $appEmulation = Mage::getSingleton('core/app_emulation');
        $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation(Bzaikia_Api_Helper_Data::CURRENT_STORE);
        switch ($this->getActionType() . $this->getOperation()) {
            /* Create */
            case self::ACTION_TYPE_ENTITY . self::OPERATION_CREATE:
                // Creation of objects is possible only when working with collection
//                $this->_critical(self::RESOURCE_METHOD_NOT_IMPLEMENTED);
//                break;
            case self::ACTION_TYPE_COLLECTION . self::OPERATION_CREATE:
                // If no of the methods(multi or single) is implemented, request body is not checked
                if (!$this->_checkMethodExist('_create') && !$this->_checkMethodExist('_multiCreate')) {
                    $this->_critical(self::RESOURCE_METHOD_NOT_IMPLEMENTED);
                }
                // If one of the methods(multi or single) is implemented, request body must not be empty
                $requestData = $this->getRequest()->getBodyParams();
                if (empty($requestData)) {
                    $this->_critical(self::RESOURCE_REQUEST_DATA_INVALID);
                }
                // The create action has the dynamic type which depends on data in the request body
                if ($this->getRequest()->isAssocArrayInRequestBody()) {
                    $this->_errorIfMethodNotExist('_create');
                    $filteredData = $this->getFilter()->in($requestData);
                    if (empty($filteredData)) {
                        $this->_critical(self::RESOURCE_REQUEST_DATA_INVALID);
                    }
                    $newItemLocation = $this->_create($filteredData);
                    $this->getResponse()->setHeader('Location', $newItemLocation);
                } else {
                    $this->_errorIfMethodNotExist('_multiCreate');
                    $filteredData = $this->getFilter()->collectionIn($requestData);
                    $this->_multiCreate($filteredData);
                    $this->_render($this->getResponse()->getMessages());
                }
                break;
            /* Retrieve */
            case self::ACTION_TYPE_ENTITY . self::OPERATION_RETRIEVE:
                $this->_errorIfMethodNotExist('_retrieve');
                $retrievedData = $this->_retrieve();
                $filteredData  = $this->getFilter()->out($retrievedData);
                $this->_render($filteredData);
                break;
            case self::ACTION_TYPE_COLLECTION . self::OPERATION_RETRIEVE:
                $this->_errorIfMethodNotExist('_retrieveCollection');
                $retrievedData = $this->_retrieveCollection();
                $filteredData  = $this->getFilter()->collectionOut($retrievedData);
                $this->_render($filteredData);
                break;
            /* Update */
            case self::ACTION_TYPE_ENTITY . self::OPERATION_UPDATE:
                $this->_errorIfMethodNotExist('_update');
                $requestData = $this->getRequest()->getBodyParams();
                if (empty($requestData)) {
                    $this->_critical(self::RESOURCE_REQUEST_DATA_INVALID);
                }
                $filteredData = $this->getFilter()->in($requestData);
                if (empty($filteredData)) {
                    $this->_critical(self::RESOURCE_REQUEST_DATA_INVALID);
                }
                $this->_update($filteredData);
                break;
            case self::ACTION_TYPE_COLLECTION . self::OPERATION_UPDATE:
                $this->_errorIfMethodNotExist('_multiUpdate');
                $requestData = $this->getRequest()->getBodyParams();
                if (empty($requestData)) {
                    $this->_critical(self::RESOURCE_REQUEST_DATA_INVALID);
                }
                $filteredData = $this->getFilter()->collectionIn($requestData);
                $this->_multiUpdate($filteredData);
                $this->_render($this->getResponse()->getMessages());

                break;
            /* Delete */
            case self::ACTION_TYPE_ENTITY . self::OPERATION_DELETE:
                $this->_errorIfMethodNotExist('_delete');
                $this->_delete();
                break;
            case self::ACTION_TYPE_COLLECTION . self::OPERATION_DELETE:
                $this->_errorIfMethodNotExist('_multiDelete');
                $requestData = $this->getRequest()->getBodyParams();
                if (empty($requestData)) {
                    $this->_critical(self::RESOURCE_REQUEST_DATA_INVALID);
                }
                $this->_multiDelete($requestData);

                break;
            default:
                $this->_critical(self::RESOURCE_METHOD_NOT_IMPLEMENTED);
                break;
        }
        $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
    }

    /**
     * @param $hash
     * @return mixed
     */
    protected function _getParam($hash) {
        $payload = Mage::helper('bzaikia_jwt')->decode($hash);
        Mage::log($payload, null, '_getParam.log');
        if ($payload->sub) return json_decode($payload->sub);
        return $payload;
    }

    /**
     * @param $userId
     * @param null $quote
     * @return bool
     */
    public function _validateAuthorization($userId, $quote = null)
    {
        $authorizationData = $_SERVER['HTTP_AUTHORIZATION'];
        $authorizationDataArr = explode(' ', $authorizationData);
        try {
            if (array_shift($authorizationDataArr) == 'Bearer') {
                $data = Mage::helper('bzaikia_jwt')->decode(array_shift($authorizationDataArr));
                if ($data->is_admin) {
                    $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
                    return true;
                }
                if ($data->user_id && ($data->user_id == $userId)) {
                    if (is_null($quote) || $quote->getCustomerId() == $data->user_id) {
                        $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);
                    }

                    return true;
                } else {
                    $this->_message = self::ERROR_USER_NOT_AUTHORIZE;
                }
                if (empty($data->user_id)) {
                    $this->_message = self::ERROR_MISSING_USER_INFO;
                }
                if ($userId === 0) {
                    $this->_message = 'Customer does not exist';
                    $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                }
                if (empty($userId)) {
                    $this->_message = 'Missing parameter user_id';
                    $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                }
            } else {
                $this->_message = self::ERROR_MISSING_AUTHORIZATION_HEADER;
            }
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::AUTHORIZATION_VALIDATION_FAIL_CODE);
            return false;
        } catch (Exception $e) {
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::AUTHORIZATION_VALIDATION_FAIL_CODE);
            $this->_message = self::ERROR_EXCEPTION_FOUND_WHEN_DECODING . $e->getMessage();
            return false;
        }
    }

    /**
     * @return bool
     */
    protected function _isLogEnable()
    {
        return true;
    }

    /**
     * @param $msg
     */
    protected function _log($msg)
    {
        Mage::log($this->getRequest()->getPathInfo(), null, self::LOG_FILE);
        Mage::log($msg, null, self::LOG_FILE);
    }
}