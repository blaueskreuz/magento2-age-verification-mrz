<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Block\Adminhtml\Order;

use BlauesKreuz\AgeVerificationMrz\Model\VerificationMethod;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Phrase;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

/**
 * The "Age verification" section on the order page in the admin.
 */
class Evidence extends Template
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(Context $context, private readonly Registry $registry, array $data = [])
    {
        parent::__construct($context, $data);
    }

    /**
     * The order that is shown.
     *
     * @return Order|null
     */
    private function getOrder(): ?Order
    {
        $order = $this->registry->registry('current_order');

        return $order instanceof Order ? $order : null;
    }

    /**
     * Whether the order had age-restricted products and the user may see the evidence.
     *
     * @return bool
     */
    public function hasEvidence(): bool
    {
        $order = $this->getOrder();

        return $order !== null
            && (int) $order->getData('mrz_required_age') > 0
            && $this->_authorization->isAllowed('BlauesKreuz_AgeVerificationMrz::view_evidence');
    }

    /**
     * The age the products needed.
     *
     * @return int
     */
    public function getRequiredAge(): int
    {
        return (int) $this->getOrder()?->getData('mrz_required_age');
    }

    /**
     * Whether the order has enough evidence (or the customer was exempt).
     *
     * @return bool
     */
    public function isCovered(): bool
    {
        $order = $this->getOrder();
        if ($order === null) {
            return false;
        }
        if ($order->getData('mrz_verified_method') === VerificationMethod::Bypass->value) {
            return true;
        }

        return $order->getData('mrz_verified_age') !== null
            && (int) $order->getData('mrz_verified_age') >= $this->getRequiredAge();
    }

    /**
     * The age the customer was verified for, empty if none.
     *
     * @return string
     */
    public function getVerifiedAge(): string
    {
        $age = $this->getOrder()?->getData('mrz_verified_age');

        return $age === null ? '' : (string) (int) $age;
    }

    /**
     * When the age was verified.
     *
     * @return string
     */
    public function getVerifiedDate(): string
    {
        $date = $this->getOrder()?->getData('mrz_verified_date');

        return $date === null ? '' : $this->formatDate(
            (new \DateTimeImmutable('@' . (int) $date))->format('c'),
            \IntlDateFormatter::MEDIUM,
            true
        );
    }

    /**
     * How the age was verified.
     *
     * @return Phrase|string
     */
    public function getMethodLabel(): Phrase|string
    {
        $method = VerificationMethod::tryFrom((string) $this->getOrder()?->getData('mrz_verified_method'));

        return $method?->label() ?? '';
    }
}
