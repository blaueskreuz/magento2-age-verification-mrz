<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds the customer attributes that hold the verified age, the date and the method.
 */
class AddCustomerAttributes implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        $this->moduleDataSetup->getConnection()->startSetup();
        /** @var CustomerSetup $setup */
        $setup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $common = ['required' => false, 'visible' => false, 'user_defined' => false, 'system' => false];
        $setup->addAttribute(Customer::ENTITY, 'mrz_verified_age', $common + [
            'type' => 'int', 'label' => 'Verified age', 'input' => 'text', 'sort_order' => 900,
        ]);
        $setup->addAttribute(Customer::ENTITY, 'mrz_verified_date', $common + [
            'type' => 'int', 'label' => 'Age verified on', 'input' => 'text', 'sort_order' => 910,
        ]);
        $setup->addAttribute(Customer::ENTITY, 'mrz_verified_method', $common + [
            'type' => 'varchar', 'label' => 'Age verification method', 'input' => 'text', 'sort_order' => 920,
        ]);
        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
