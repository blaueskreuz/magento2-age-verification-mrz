<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reads the settings of the module (Stores > Configuration > Sales > Age Verification (MRZ)).
 */
class Config
{
    private const PATH = 'blaueskreuz_age_verification/general/';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Whether age verification is turned on.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'enabled', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * The minimum age of products that do not set their own.
     *
     * @param int|null $storeId
     * @return int
     */
    public function getDefaultMinimumAge(?int $storeId = null): int
    {
        return max(0, (int) $this->scopeConfig->getValue(
            self::PATH . 'default_minimum_age',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
    }

    /**
     * The ages that can be chosen for a product, sorted.
     *
     * @param int|null $storeId
     * @return int[]
     */
    public function getAllowedAges(?int $storeId = null): array
    {
        $text = (string) $this->scopeConfig->getValue(self::PATH . 'allowed_ages', ScopeInterface::SCOPE_STORE, $storeId);
        $ages = array_filter(array_map('intval', preg_split('/[\s,;]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []));
        $ages = array_values(array_unique($ages));
        sort($ages);

        return $ages;
    }

    /**
     * Whether customers may verify their age in their account.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isAccountVerificationAllowed(?int $storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && $this->scopeConfig->isSetFlag(self::PATH . 'allow_account_verification', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * Whether the youth-protection message is shown on the age verification page.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isYouthMessageShown(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'show_youth_message', ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * The youth-protection message, empty if it is switched off.
     *
     * The text set in the settings is shown as it is. Without one, the default text is used, in the language of
     * the store.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getYouthMessage(?int $storeId = null): string
    {
        if (!$this->isYouthMessageShown($storeId)) {
            return '';
        }
        $text = trim((string) $this->scopeConfig->getValue(
            self::PATH . 'youth_message',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));

        return $text !== '' ? $text : (string) __('Together for youth protection!');
    }

    /**
     * The customer groups that are never asked.
     *
     * @param int|null $storeId
     * @return int[]
     */
    public function getExemptCustomerGroupIds(?int $storeId = null): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::PATH . 'exempt_customer_groups',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $value === '' ? [] : array_map('intval', explode(',', $value));
    }
}
