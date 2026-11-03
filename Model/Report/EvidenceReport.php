<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model\Report;

use BlauesKreuz\AgeVerificationMrz\Model\EvidenceStatus;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationMethod;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;

/**
 * The orders with age-restricted products and their evidence, for the report page and its CSV download.
 */
class EvidenceReport
{
    /**
     * @param CollectionFactory $collectionFactory
     * @param TimezoneInterface $timezone
     */
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly TimezoneInterface $timezone
    ) {
    }

    /**
     * The orders that needed an age, newest first.
     *
     * @param string $from first day, as YYYY-MM-DD (shop time zone), empty for no limit
     * @param string $to last day, as YYYY-MM-DD (shop time zone), empty for no limit
     * @param bool $onlyMissing only the orders without enough evidence
     * @return Collection
     */
    public function getCollection(string $from = '', string $to = '', bool $onlyMissing = false): Collection
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('main_table.mrz_required_age', ['gt' => 0]);
        if ($this->isDate($from)) {
            $collection->addFieldToFilter(
                'main_table.created_at',
                ['gteq' => $this->timezone->convertConfigTimeToUtc($from . ' 00:00:00')]
            );
        }
        if ($this->isDate($to)) {
            $collection->addFieldToFilter(
                'main_table.created_at',
                ['lteq' => $this->timezone->convertConfigTimeToUtc($to . ' 23:59:59')]
            );
        }
        if ($onlyMissing) {
            $select = $collection->getSelect();
            $select->where('main_table.mrz_verified_method IS NULL OR main_table.mrz_verified_method <> ?', 'bypass');
            $select->where('main_table.mrz_verified_age IS NULL OR main_table.mrz_verified_age < '
                . 'main_table.mrz_required_age');
        }
        $collection->setOrder('main_table.created_at', 'DESC');

        return $collection;
    }

    /**
     * Whether a text is a date as the date field of the filter sends it.
     *
     * @param string $value
     * @return bool
     */
    public function isDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }
        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    /**
     * The column headings.
     *
     * @return string[]
     */
    public function getHeadings(): array
    {
        return [
            (string) __('Order'),
            (string) __('Placed on'),
            (string) __('Customer'),
            (string) __('Email'),
            (string) __('Age required'),
            (string) __('Verified for age'),
            (string) __('Verified on'),
            (string) __('How'),
            (string) __('Evidence'),
        ];
    }

    /**
     * The cells of one order.
     *
     * @param Order $order
     * @return string[]
     */
    public function getRow(Order $order): array
    {
        $required = (int) $order->getData('mrz_required_age');
        $age = $order->getData('mrz_verified_age');
        $date = $order->getData('mrz_verified_date');
        $method = $order->getData('mrz_verified_method');
        $status = EvidenceStatus::fromOrder($required, $age === null ? null : (int) $age, $method);

        return [
            (string) $order->getIncrementId(),
            $this->timezone->date(new \DateTime((string) $order->getCreatedAt()))->format('Y-m-d H:i'),
            trim((string) $order->getCustomerName()),
            (string) $order->getCustomerEmail(),
            (string) $required,
            $age === null ? '' : (string) (int) $age,
            $date === null ? '' : $this->timezone->date((int) $date)->format('Y-m-d H:i'),
            (string) (VerificationMethod::tryFrom((string) $method)?->label() ?? ''),
            (string) $status->label(),
        ];
    }
}
