<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Checks and tidies the list of ages that can be chosen: whole ages from 1 to 99.
 */
class AllowedAges extends Value
{
    /**
     * @inheritdoc
     *
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $parts = preg_split('/[\s,;]+/', trim((string) $this->getValue()), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ages = [];
        foreach ($parts as $part) {
            if (!ctype_digit($part) || (int) $part < 1 || (int) $part > 99) {
                throw new LocalizedException(__('Enter whole ages between 1 and 99, separated by commas.'));
            }
            $ages[] = (int) $part;
        }
        if ($ages === []) {
            throw new LocalizedException(__('Enter whole ages between 1 and 99, separated by commas.'));
        }
        $ages = array_values(array_unique($ages));
        sort($ages);
        $this->setValue(implode(',', $ages));

        return parent::beforeSave();
    }
}
