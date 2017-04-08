<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Helper_Log extends Mage_Core_Helper_Abstract
{
    public function log($message)
    {
        Mage::log($message, null, 'bzaikia_api_error.log');
    }
}