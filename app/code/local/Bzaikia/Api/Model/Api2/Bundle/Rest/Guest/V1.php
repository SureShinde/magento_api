<?php

/**
 * Author: Rowan Burgess
 */
class Bzaikia_Api_Model_Api2_Bundle_Rest_Guest_V1 extends Bzaikia_Api_Model_Api2_Bundle
{
    const SKU_FIELD = 'product_SKU';
    const QUANTITY_FIELD = 'quantity';

    const ERROR_MESSAGE_NOT_EXIST_SIMPLE_PRODUCT = 'the supplied product sku does not match with any product, please make sure you have correct one';
    const ERROR_MESSAGE_QUANTITY_NEGATIVE = 'the supplied product quantity is less then 0, please make sure it is positive number';

    public function _create(array $filteredData)
    {
        $name = $filteredData['bundle_name'];
        $bundleIdExternal = $filteredData['bundle_id_external'];
        $productItems = $filteredData['product_items'];
        if ($this->_validate($filteredData)) {
            $this->_createBundle($name, $bundleIdExternal, $productItems);
            foreach ($productItems as $product) {
                $this->_applyBundleQuantity($product[self::SKU_FIELD], $product[self::QUANTITY_FIELD]);
            }
        }

    }

    /**
     * @param $name
     * @param $bundleIdExternal
     * @param $productItems
     */
    protected function _createBundle($name, $bundleIdExternal, $productItems)
    {
        $bundleProduct = Mage::getModel('catalog/product');
        $bundleProduct
            ->setWebsiteIds(array(1))//website ID the product is assigned to, as an array
            ->setAttributeSetId(4)//ID of a attribute set named 'default'
            ->setTypeId('bundle')//product type
            ->setBundleIdExternal($bundleIdExternal)
            ->setCreatedAt(strtotime('now'))//product creation time
            ->setSkuType(0)//SKU type (0 - dynamic, 1 - fixed)
            ->setName($name)//product name
            ->setWeightType(0)//weight type (0 - dynamic, 1 - fixed)
            ->setShipmentType(0)//shipment type (0 - together, 1 - separately)
            ->setStatus(1)//product status (1 - enabled, 2 - disabled)
            ->setVisibility(Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH)//catalog and search visibility
            ->setPriceType(0)//price type (0 - dynamic, 1 - fixed)
            ->setPriceView(0)//price view (0 - price range, 1 - as low as)
            ->setMetaTitle($name)
            ->setMetaKeyword($name)
            ->setMetaDescription($name)
            ->setDescription($name)
            ->setShortDescription($name)
            ->setMediaGallery(array('images' => array(), 'values' => array()))//media gallery initialization
            ->setStockData(array(
                    'use_config_manage_stock' => 1, //'Use config settings' checkbox
                    'manage_stock' => 1, //manage stock
                    'is_in_stock' => 1, //Stock Availability
                )
            );


        $bundleOptions = array(
            '0' => array( //option id (0, 1, 2, etc)
                'title' => 'item01', //option title
                'option_id' => '',
                'delete' => '',
                'type' => 'multi', //option type
                'required' => '1', //is option required
                'position' => '1' //option position
            )
        );
        $i = 0;
        foreach ($productItems as $product) {
            $id = Mage::getModel('catalog/product')->getIdBySku($product[self::SKU_FIELD]);

            $bundleSelections[0][$i++] = array( //selection ID of the option (first product under this option (option ID) would have ID of 0, second an ID of 1, etc)
                'product_id' => $id, //if of a product in selection
                'delete' => '',
                'selection_qty' => $product[self::QUANTITY_FIELD],
                'selection_can_change_qty' => 0,
                'position' => 0,
                'is_default' => 1
            );
        }


        //flags for saving custom options/selections
        $bundleProduct->setCanSaveCustomOptions(true);
        $bundleProduct->setCanSaveBundleSelections(true);
        $bundleProduct->setAffectBundleProductSelections(true);

        //registering a product because of Mage_Bundle_Model_Selection::_beforeSave
        Mage::register('product', $bundleProduct);

        //setting the bundle options and selection data
        $bundleProduct->setBundleOptionsData($bundleOptions);
        $bundleProduct->setBundleSelectionsData($bundleSelections);
        try {
            $bundleProduct->save();
            $this->getResponse()->setBody('created the bundle successfully');
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SUCCESSFUL_CODE);

        } catch (Exception $e) {
            $this->getResponse()->setBody($e->getMessage());
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::SERVER_ERROR_CODE);
        }
    }

    /**
     * @param $sku
     * @param $qty
     */
    protected function _applyBundleQuantity($sku, $qty)
    {
        $id = Mage::getModel('catalog/product')->getIdBySku($sku);

        $action = Mage::getModel('catalog/resource_product_action');
        $action->updateAttributes(array($id),
            array('bundle_quantity' => $qty), 1);
    }

    /**
     * @param $filteredData
     * @return bool
     */
    protected function _validate($filteredData)
    {
        $name = $filteredData['bundle_name'];
        $bundleIdExternal = $filteredData['bundle_id_external'];
        $productItems = $filteredData['product_items'];

        if (empty($name) || empty($bundleIdExternal) || empty($productItems)) {
            $this->getResponse()->setBody(self::ERROR_MESSAGE_LACKING_PARAM);
            $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
            return false;
        }

        foreach ($productItems as $productItem) {
            if (!$productItem[self::SKU_FIELD] || !$productItem[self::QUANTITY_FIELD]) {
                $this->getResponse()->setBody(self::ERROR_MESSAGE_LACKING_PARAM);
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                return false;
            }

            $productId = Mage::getModel('catalog/product')->getIdBySku($productItem[self::SKU_FIELD]);
            if (!$productId) {
                $this->getResponse()->setBody(self::ERROR_MESSAGE_NOT_EXIST_SIMPLE_PRODUCT);
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                return false;
            }

            if ($productItem[self::QUANTITY_FIELD] <= 0) {
                $this->getResponse()->setBody(self::ERROR_MESSAGE_QUANTITY_NEGATIVE);
                $this->getResponse()->setHttpResponseCode(Bzaikia_Api_Helper_Data::CLIENT_ERROR_CODE);
                return false;
            }
        }

        return true;

    }
}