<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model;

use Magento\Framework\Phrase;

/**
 * How an age was verified. On a customer the first three apply; an order adds the ones for an age that was
 * verified earlier and saved on the account, and for an exempt customer group.
 */
enum VerificationMethod: string
{
    case Checkout = 'checkout';
    case Account = 'profile';
    case Manual = 'manual';
    case AccountCheckout = 'account_checkout';
    case AccountAccount = 'account_profile';
    case AccountManual = 'account_manual';
    case Bypass = 'bypass';

    /**
     * A description for people.
     *
     * @return Phrase
     */
    public function label(): Phrase
    {
        return match ($this) {
            self::Checkout => __('At this checkout'),
            self::Account => __('In the customer account'),
            self::Manual => __('Set by staff'),
            self::AccountCheckout => __('Earlier checkout, saved on the account'),
            self::AccountAccount => __('Customer account, saved on the account'),
            self::AccountManual => __('Set by staff, saved on the account'),
            self::Bypass => __('Exempt customer group, not asked'),
        };
    }

    /**
     * The method as it applies to an order that uses the age saved on the account.
     *
     * @return self
     */
    public function forOrderFromAccount(): self
    {
        return match ($this) {
            self::Checkout => self::AccountCheckout,
            self::Account => self::AccountAccount,
            self::Manual => self::AccountManual,
            default => $this,
        };
    }
}
