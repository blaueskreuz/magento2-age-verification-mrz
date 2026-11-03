<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Observer;

use BlauesKreuz\AgeVerificationMrz\Model\Config;
use BlauesKreuz\AgeVerificationMrz\Model\VerificationManager;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Writes the evidence onto the order when it is placed: the age the products needed, the age the customer was
 * verified for, when and how. Only ages, dates and the method are copied, never anything from the document.
 */
class RecordEvidence implements ObserverInterface
{
    /**
     * @param Config $config
     * @param VerificationManager $manager
     */
    public function __construct(
        private readonly Config $config,
        private readonly VerificationManager $manager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getData('order');
        $quote = $observer->getEvent()->getData('quote');
        if (!$order || !$quote || !$this->config->isEnabled((int) $quote->getStoreId())) {
            return;
        }
        $evidence = $this->manager->getEvidence($quote);
        if ($evidence['required'] === 0) {
            return;
        }
        $order->setData('mrz_required_age', $evidence['required']);
        $order->setData('mrz_verified_age', $evidence['age']);
        $order->setData('mrz_verified_date', $evidence['date']);
        $order->setData('mrz_verified_method', $evidence['method']);
    }
}
