<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model;

use Magento\Framework\Phrase;

/**
 * Whether an order that needed an age has the evidence for it.
 */
enum EvidenceStatus: string
{
    case Verified = 'verified';
    case Exempt = 'exempt';
    case NoEvidence = 'none';

    /**
     * The status of an order, from what was written on it when it was placed.
     *
     * @param int $requiredAge
     * @param int|null $verifiedAge
     * @param string|null $method
     * @return self
     */
    public static function fromOrder(int $requiredAge, ?int $verifiedAge, ?string $method): self
    {
        if ($method === VerificationMethod::Bypass->value) {
            return self::Exempt;
        }

        return $verifiedAge !== null && $verifiedAge >= $requiredAge ? self::Verified : self::NoEvidence;
    }

    /**
     * A description for people.
     *
     * @return Phrase
     */
    public function label(): Phrase
    {
        return match ($this) {
            self::Verified => __('Verified'),
            self::Exempt => __('Exempt'),
            self::NoEvidence => __('No evidence'),
        };
    }
}
