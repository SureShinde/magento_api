<?php
/**
 * Author: Rowan Burgess
 */

$installer = $this;

$installer->startSetup();

$resource = Mage::getSingleton('core/resource');
$saleQuote = $resource->getTableName('sales/quote');
$installer->getConnection()->addColumn($saleQuote, 'external_bundle_id', 'varchar(99)');

$saleOrder = $resource->getTableName('sales/order');
$installer->getConnection()->addColumn($saleOrder, 'external_bundle_id', 'varchar(99)');

$installer->endSetup();