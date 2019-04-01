<?php

$installer = $this;

$installer->startSetup();

$setup = Mage::getModel('customer/entity_setup', 'core_setup');

$setup->addAttribute('customer', $this::CONSUMER_ID_DATA_KEY, array(
    'type'             => 'int',
    'input'            => 'hidden',
    'global'           => 1,
    'visible'          => 0,
    'required'         => 0,
    'user_defined'     => 0,
    'default'          => '',
    'visible_on_front' => 1,
    'source'           => null,
    'unique'           => true
));

$installer->endSetup();
