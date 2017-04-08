<?php
/**
 * Author: Rowan Burgess
 */

$installer = $this;

$installer->startSetup();

$installer->addAttribute('catalog_product', 'bundle_id_external', array(
    'type'              => 'int',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Django Product Id',
    'input'             => 'text',
    'class'             => '',
    'source'            => '',
    'required'          => false,
    'user_defined'      => false,
    'default'           => '',
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'unique'            => false,
    'apply_to'          => 'bundle',
    'is_configurable'   => false,
    'group'             => 'General'
));

$installer->addAttribute('catalog_product', 'bundle_quantity', array(
    'type'              => 'int',
    'backend'           => '',
    'frontend'          => '',
    'label'             => 'Django Product Id',
    'input'             => 'text',
    'class'             => '',
    'source'            => '',
    'required'          => false,
    'user_defined'      => false,
    'default'           => '',
    'searchable'        => false,
    'filterable'        => false,
    'comparable'        => false,
    'visible_on_front'  => false,
    'unique'            => false,
    'apply_to'          => 'simple',
    'is_configurable'   => false,
    'group'             => 'General'
));

$installer->endSetup();