<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Plugin\Customer;

use BlauesKreuz\AgeVerificationMrz\Observer\SaveVerifiedAgeFromAdmin;
use Magento\Customer\Model\Customer\DataProviderWithDefaultAddresses;
use Magento\Framework\AuthorizationInterface;

/**
 * Makes the verified age read-only in the customer form for users who may not change it.
 * (The save is refused for them as well, see SaveVerifiedAgeFromAdmin.)
 */
class DisableVerifiedAgeField
{
    /**
     * @param AuthorizationInterface $authorization
     */
    public function __construct(private readonly AuthorizationInterface $authorization)
    {
    }

    /**
     * Disables the field in the meta data of the form.
     *
     * @param DataProviderWithDefaultAddresses $subject
     * @param array $result
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterGetMeta(DataProviderWithDefaultAddresses $subject, array $result): array
    {
        if ($this->authorization->isAllowed(SaveVerifiedAgeFromAdmin::RESOURCE)) {
            return $result;
        }
        $path = ['customer', 'children', 'mrz_verified_age', 'arguments', 'data', 'config'];
        $node = &$result;
        foreach ($path as $key) {
            if (!isset($node[$key]) || !is_array($node[$key])) {
                return $result;
            }
            $node = &$node[$key];
        }
        $node['disabled'] = true;

        return $result;
    }
}
