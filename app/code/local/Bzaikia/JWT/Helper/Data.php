<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_JWT_Helper_Data extends Mage_Core_Helper_Abstract
{
    const KEY = 'yolo111';

    public function __construct()
    {
        $this->_registerAutoloader();
    }

    /**
     * Register our custom autoloader, this is needed because Magento can't handle PHP's namespaces.
     *
     * @return $this
     */
    protected function _registerAutoloader()
    {
        require_once(Mage::getBaseDir('lib') . '/php-jwt/src/JWT.php');

        spl_autoload_register(array($this, 'load'), true, true);

        return $this;
    }

    /**
     * This function autoloads JWT classes
     *
     * @param string $class
     */
    protected static function load($class)
    {
        /**
         * Project-specific namespace prefix
         */
        $prefix = 'JWT';

        /**
         * Base directory for the namespace prefix
         */
        $base_dir = Mage::getBaseDir('lib') . '/php-jwt/src/';

        if (strpos($class, $prefix) === false) {
            /**
             * No, move to the next registered autoloader
             */
            return;
        }

        /**
         * Get the relative class name
         */
        $class_directory = str_replace('\\', '/', $class);

        if (substr($class_directory, -9) == 'Exception') {
            $class_array = explode('/', $class_directory);
            $class_directory = $class_array[count($class_array) - 1];
        }

        /**
         * Replace the namespace prefix with the base directory, replace namespace
         * separators with directory separators in the relative class name, append
         * with .php
         */
        $file = $base_dir . $class_directory . '.php';

        /**
         * if the file exists, require it
         */
        if (file_exists($file)) {
            require $file;
        }
    }

    /**
     * @param $jwt
     * @return object
     */
    public function decode($jwt)
    {
        date_default_timezone_set("UTC");
        $data = \Firebase\JWT\JWT::decode($jwt, self::KEY, array('HS256'));
        return $data;
    }

    public function encode($data)
    {
        return \Firebase\JWT\JWT::encode($data, self::KEY);
    }
}