<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Plugin\Quote;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationManager;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\QuoteManagement;

/**
 * Refuses to place an order that is not old enough, whichever way it is placed (checkout, REST, GraphQL).
 * Orders created in the admin by staff are not checked.
 */
class EnforceAgeOnSubmit
{
    /**
     * @param Config $config
     * @param VerificationManager $manager
     * @param State $appState
     */
    public function __construct(
        private readonly Config $config,
        private readonly VerificationManager $manager,
        private readonly State $appState
    ) {
    }

    /**
     * Stops the order if the age check is missing.
     *
     * @param QuoteManagement $subject
     * @param Quote $quote
     * @param array $orderData
     * @return void
     * @throws LocalizedException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeSubmit(QuoteManagement $subject, Quote $quote, array $orderData = []): void
    {
        if (!$this->config->isEnabled((int) $quote->getStoreId()) || $this->isAdminArea()) {
            return;
        }
        if (!$this->manager->isSatisfied($quote)) {
            throw new LocalizedException(__('Please verify your age before placing this order.'));
        }
    }

    /**
     * Whether the code runs in the admin.
     *
     * @return bool
     */
    private function isAdminArea(): bool
    {
        try {
            return $this->appState->getAreaCode() === Area::AREA_ADMINHTML;
        } catch (LocalizedException) {
            return false;
        }
    }
}
