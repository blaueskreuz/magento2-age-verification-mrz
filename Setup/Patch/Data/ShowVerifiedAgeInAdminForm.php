<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Customer\Model\ResourceModel\Attribute as AttributeResource;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Shows the verified age in the customer form of the admin, so that staff can set or clear it.
 * Customers never see it: the attribute is not used in any form of the storefront.
 */
class ShowVerifiedAgeInAdminForm implements DataPatchInterface
{
    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param CustomerSetupFactory $customerSetupFactory
     * @param EavConfig $eavConfig
     * @param AttributeResource $attributeResource
     */
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory,
        private readonly EavConfig $eavConfig,
        private readonly AttributeResource $attributeResource
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
        $setup->updateAttribute(Customer::ENTITY, 'mrz_verified_age', 'is_visible', 1);
        $setup->updateAttribute(Customer::ENTITY, 'mrz_verified_age', 'sort_order', 900);
        $setup->updateAttribute(
            Customer::ENTITY,
            'mrz_verified_age',
            'note',
            'Set by staff. Leave empty if the age has not been verified. The date is set to now when the age changes.'
        );

        $attribute = $this->eavConfig->getAttribute(Customer::ENTITY, 'mrz_verified_age');
        $attribute->setData('used_in_forms', ['adminhtml_customer']);
        $this->attributeResource->save($attribute);
        $this->moduleDataSetup->getConnection()->endSetup();

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function getDependencies()
    {
        return [AddCustomerAttributes::class];
    }

    /**
     * @inheritdoc
     */
    public function getAliases()
    {
        return [];
    }
}
