<?php
/**
 * Author: Rowan Burgess
 */
/* @var $installer Mage_Core_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

$installer->addAttribute('customer', 'user_id_external', array(
    'label' => 'User Id External',
    'type' => 'int',
    'input' => 'text',
    'visible' => true,
    'required' => false,
    'comment' => 'ID of the external Django user',
));

$userIdExternalAttribute = Mage::getSingleton('eav/config')
    ->getAttribute('customer', 'user_id_external');
$userIdExternalAttribute->setData('used_in_forms', array(
    'adminhtml_customer'
));
$userIdExternalAttribute->save();

$installer->addAttribute('customer', 'is_affiliate', array(
    'label' => 'Is Affiliate',
    'type' => 'int',
    'input' => 'select',
    'visible' => true,
    'required' => false,
    'source' => 'eav/entity_attribute_source_boolean',
    'comment' => 'Dictates whether the user is an affiliate or it’s a normal user (trainee)'
));

$isAffiliate = Mage::getSingleton('eav/config')
    ->getAttribute('customer', 'is_affiliate');
$isAffiliate->setData('used_in_forms', array(
    'adminhtml_customer'
));
$isAffiliate->save();

$installer->addAttribute('customer', 'affiliate_id_external', array(
    'label' => 'Affiliate Id External',
    'type' => 'int',
    'input' => 'text',
    'visible' => true,
    'required' => false,
    'comment' => '(Optional) ID of the external Django affiliate. If this user is affiliate this would be null (Long)'
));

$affiliateIdExternal = Mage::getSingleton('eav/config')
    ->getAttribute('customer', 'affiliate_id_external');
$affiliateIdExternal->setData('used_in_forms', array(
    'adminhtml_customer'
));
$affiliateIdExternal->save();


$installer->addAttribute('customer', 'partner', array(
    'label' => 'Partner',
    'type' => 'varchar',
    'input' => 'text',
    'visible' => true,
    'required' => false,
    'comment' => 'Future proofing, to track if the user signed up through TPA or SDK partners (String)'
));

$parter = Mage::getSingleton('eav/config')
    ->getAttribute('customer', 'partner');

$parter->setData('used_in_forms', array(
    'adminhtml_customer'
));

$parter->save();


$installer->endSetup();