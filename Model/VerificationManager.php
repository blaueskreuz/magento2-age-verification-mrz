<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzResult;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzValidator;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\VerifiedAge;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;

/**
 * Decides whether a cart is old enough and records the result of a check.
 *
 * Registered customers keep the age on their account; guests keep it on the cart (and so on the order).
 * The MRZ itself is handed to the validator and never stored or logged.
 */
class VerificationManager
{
    /**
     * @param Config $config
     * @param RequiredAgeResolver $resolver
     * @param CustomerRegistry $customerRegistry
     * @param CustomerResource $customerResource
     * @param CartRepositoryInterface $cartRepository
     * @param TimezoneInterface $timezone
     * @param MrzValidator $validator
     */
    public function __construct(
        private readonly Config $config,
        private readonly RequiredAgeResolver $resolver,
        private readonly CustomerRegistry $customerRegistry,
        private readonly CustomerResource $customerResource,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly TimezoneInterface $timezone,
        private readonly MrzValidator $validator
    ) {
    }

    /**
     * Whether the customer of the cart is never asked.
     *
     * @param CartInterface $quote
     * @return bool
     */
    public function isExempt(CartInterface $quote): bool
    {
        /** @var Quote $quote */
        $groupId = $quote->getCustomerGroupId();

        return $groupId !== null
            && in_array((int) $groupId, $this->config->getExemptCustomerGroupIds((int) $quote->getStoreId()), true);
    }

    /**
     * The age the cart needs from its customer, 0 if nothing is asked.
     *
     * @param CartInterface $quote
     * @return int
     */
    public function getRequiredAge(CartInterface $quote): int
    {
        return $this->isExempt($quote) ? 0 : $this->resolver->resolve($quote);
    }

    /**
     * Whether the cart may be ordered as far as age is concerned.
     *
     * @param CartInterface $quote
     * @return bool
     */
    public function isSatisfied(CartInterface $quote): bool
    {
        $required = $this->getRequiredAge($quote);

        return $required === 0 || $this->getVerifiedAge($quote) >= $required;
    }

    /**
     * The age the customer is known to be at least today, 0 if unknown.
     *
     * @param CartInterface $quote
     * @return int
     */
    public function getVerifiedAge(CartInterface $quote): int
    {
        /** @var Quote $quote */
        $best = $this->lowerBound($quote->getData('mrz_verified_age'), $quote->getData('mrz_verified_date'));
        if ($quote->getCustomerId()) {
            $customer = $this->customerRegistry->retrieve((int) $quote->getCustomerId());
            $best = max($best, $this->lowerBound(
                $customer->getData('mrz_verified_age'),
                $customer->getData('mrz_verified_date')
            ));
        }

        return $best;
    }

    /**
     * Checks an MRZ and, if it is valid, records the age on the cart (and the customer account).
     *
     * @param CartInterface $quote
     * @param string $mrz
     * @param VerificationMethod $method
     * @return MrzResult
     */
    public function verify(CartInterface $quote, string $mrz, VerificationMethod $method): MrzResult
    {
        $result = $this->validator->validate($mrz, $this->now());
        if ($result->isValid()) {
            $this->record($quote, (int) $result->getAge(), $method);
        }

        return $result;
    }

    /**
     * Records a verified age. An age is only replaced by one that is at least as strong.
     *
     * @param CartInterface $quote
     * @param int $age
     * @param VerificationMethod $method
     * @return void
     */
    public function record(CartInterface $quote, int $age, VerificationMethod $method): void
    {
        /** @var Quote $quote */
        $now = $this->now();
        if ($age >= $this->lowerBound($quote->getData('mrz_verified_age'), $quote->getData('mrz_verified_date'))) {
            $quote->setData('mrz_verified_age', $age);
            $quote->setData('mrz_verified_date', $now->getTimestamp());
            $quote->setData('mrz_verified_method', $method->value);
            $this->cartRepository->save($quote);
        }
        if ($quote->getCustomerId()) {
            $this->recordForCustomer((int) $quote->getCustomerId(), $age, $method);
        }
    }

    /**
     * Records a verified age on the account of a customer. An age is only replaced by one that is at least as
     * strong.
     *
     * @param int $customerId
     * @param int $age
     * @param VerificationMethod $method
     * @return bool whether the age was saved (false: the account already holds a higher age)
     */
    public function recordForCustomer(int $customerId, int $age, VerificationMethod $method): bool
    {
        $customer = $this->customerRegistry->retrieve($customerId);
        $known = $this->lowerBound($customer->getData('mrz_verified_age'), $customer->getData('mrz_verified_date'));
        if ($age < $known) {
            return false;
        }
        $customer->setData('mrz_verified_age', $age);
        $customer->setData('mrz_verified_date', $this->now()->getTimestamp());
        $customer->setData('mrz_verified_method', $method->value);
        foreach (['mrz_verified_age', 'mrz_verified_date', 'mrz_verified_method'] as $code) {
            $this->customerResource->saveAttribute($customer, $code);
        }

        return true;
    }

    /**
     * Checks an MRZ and, if it is valid, records the age on the account of a customer (no cart needed).
     *
     * @param int $customerId
     * @param string $mrz
     * @param VerificationMethod $method
     * @return array{result: MrzResult, saved: bool}
     */
    public function verifyForCustomer(int $customerId, string $mrz, VerificationMethod $method): array
    {
        $result = $this->validator->validate($mrz, $this->now());
        $saved = $result->isValid() && $this->recordForCustomer($customerId, (int) $result->getAge(), $method);

        return ['result' => $result, 'saved' => $saved];
    }

    /**
     * What is known about the age of a customer: the age they are at least today, and when it was verified.
     *
     * @param int $customerId
     * @return array{age: int, date: ?int}
     */
    public function getCustomerStatus(int $customerId): array
    {
        $customer = $this->customerRegistry->retrieve($customerId);
        $age = $this->lowerBound($customer->getData('mrz_verified_age'), $customer->getData('mrz_verified_date'));
        $date = $customer->getData('mrz_verified_date');

        return ['age' => $age, 'date' => $age > 0 && $date !== null && $date !== '' ? (int) $date : null];
    }

    /**
     * The evidence of a cart, to be copied to the order when it is placed.
     *
     * @param CartInterface $quote
     * @return array{required: int, age: ?int, date: ?int, method: ?string}
     */
    public function getEvidence(CartInterface $quote): array
    {
        /** @var Quote $quote */
        $required = $this->resolver->resolve($quote);
        if ($required === 0) {
            return ['required' => 0, 'age' => null, 'date' => null, 'method' => null];
        }
        if ($this->isExempt($quote)) {
            return ['required' => $required, 'age' => null, 'date' => null, 'method' => VerificationMethod::Bypass->value];
        }
        $source = $quote;
        $fromAccount = false;
        $quoteAge = $this->lowerBound($quote->getData('mrz_verified_age'), $quote->getData('mrz_verified_date'));
        if ($quote->getCustomerId()) {
            $customer = $this->customerRegistry->retrieve((int) $quote->getCustomerId());
            $accountAge = $this->lowerBound($customer->getData('mrz_verified_age'), $customer->getData('mrz_verified_date'));
            if ($accountAge > $quoteAge) {
                $source = $customer;
                $fromAccount = true;
            }
        }
        $age = $source->getData('mrz_verified_age');
        $method = VerificationMethod::tryFrom((string) $source->getData('mrz_verified_method'));
        if ($age === null || $method === null) {
            return ['required' => $required, 'age' => null, 'date' => null, 'method' => null];
        }
        if ($fromAccount) {
            $method = $method->forOrderFromAccount();
        }

        return [
            'required' => $required,
            'age' => (int) $age,
            'date' => (int) $source->getData('mrz_verified_date'),
            'method' => $method->value,
        ];
    }

    /**
     * The age somebody is at least today, given an earlier verification.
     *
     * @param mixed $age
     * @param mixed $date
     * @return int
     */
    private function lowerBound(mixed $age, mixed $date): int
    {
        if ($age === null || $age === '' || $date === null || $date === '') {
            return 0;
        }
        $verifiedOn = (new \DateTimeImmutable('@' . (int) $date))->setTimezone($this->now()->getTimezone());

        return VerifiedAge::atLeast((int) $age, $verifiedOn, $this->now());
    }

    /**
     * The current time in the time zone of the shop.
     *
     * @return \DateTimeImmutable
     */
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($this->timezone->getConfigTimezone()));
    }
}
