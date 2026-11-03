<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Observer;

use BlauesKreuz\AgeVerificationMrz\Model\VerificationMethod;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\CustomerRegistry;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Applies the verified age that staff entered in the customer form of the admin.
 *
 * Only users with the permission may change it; for others the stored values are kept. An age that is set or
 * changed by staff is marked "set by staff" and dated now; an empty field clears the age, date and method.
 */
class SaveVerifiedAgeFromAdmin implements ObserverInterface
{
    public const RESOURCE = 'BlauesKreuz_AgeVerificationMrz::set_customer_age';

    private const CODES = ['mrz_verified_age', 'mrz_verified_date', 'mrz_verified_method'];

    /**
     * @param AuthorizationInterface $authorization
     * @param CustomerRegistry $customerRegistry
     * @param DateTime $dateTime
     */
    public function __construct(
        private readonly AuthorizationInterface $authorization,
        private readonly CustomerRegistry $customerRegistry,
        private readonly DateTime $dateTime
    ) {
    }

    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        /** @var CustomerInterface $customer */
        $customer = $observer->getEvent()->getData('customer');
        $stored = $this->storedValues($customer);

        if (!$this->authorization->isAllowed(self::RESOURCE)) {
            $this->apply($customer, $stored);

            return;
        }

        $posted = $customer->getCustomAttribute('mrz_verified_age')?->getValue();
        if ($posted === null || trim((string) $posted) === '') {
            $this->apply($customer, ['mrz_verified_age' => null, 'mrz_verified_date' => null,
                'mrz_verified_method' => null]);

            return;
        }
        if (!ctype_digit(trim((string) $posted)) || (int) $posted < 1 || (int) $posted > 99) {
            throw new LocalizedException(__('Enter a whole age between 1 and 99, or leave the field empty.'));
        }
        $age = (int) $posted;
        $unchanged = $stored['mrz_verified_age'] !== null && (int) $stored['mrz_verified_age'] === $age
            && $stored['mrz_verified_date'] !== null;
        $this->apply($customer, $unchanged ? $stored : [
            'mrz_verified_age' => $age,
            'mrz_verified_date' => $this->dateTime->gmtTimestamp(),
            'mrz_verified_method' => VerificationMethod::Manual->value,
        ]);
    }

    /**
     * The values that are stored for the customer.
     *
     * @param CustomerInterface $customer
     * @return array<string, mixed>
     */
    private function storedValues(CustomerInterface $customer): array
    {
        $values = array_fill_keys(self::CODES, null);
        if (!$customer->getId()) {
            return $values;
        }
        try {
            $model = $this->customerRegistry->retrieve((int) $customer->getId());
        } catch (NoSuchEntityException) {
            return $values;
        }
        foreach (self::CODES as $code) {
            $value = $model->getData($code);
            $values[$code] = ($value === '' ? null : $value);
        }

        return $values;
    }

    /**
     * Sets the three values on the customer that is about to be saved.
     *
     * @param CustomerInterface $customer
     * @param array<string, mixed> $values
     * @return void
     */
    private function apply(CustomerInterface $customer, array $values): void
    {
        foreach (self::CODES as $code) {
            $customer->setCustomAttribute($code, $values[$code]);
        }
    }
}
