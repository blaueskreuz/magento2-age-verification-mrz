<?php
/**
 * Copyright © Blaues Kreuz. Licensed under GPL-2.0-or-later.
 */
declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Model;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzLayout;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzResult;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Phrase;

/**
 * Reads the entry of the guided input from a request and words the errors. Used by every page that asks for an
 * MRZ, so that they all behave the same. The entry is only read to be checked; it is not kept anywhere.
 */
class MrzForm
{
    /**
     * The document types the customer can choose from, keyed by their form value.
     *
     * @return array<string, MrzFormat>
     */
    public static function formats(): array
    {
        return ['td1' => MrzFormat::TD1, 'td3' => MrzFormat::TD3, 'td2' => MrzFormat::TD2];
    }

    /**
     * The chosen document type and the MRZ the entry makes up.
     *
     * @param RequestInterface $request
     * @return array{type: string, mrz: ?string} the MRZ is null if nothing was filled in
     */
    public function read(RequestInterface $request): array
    {
        $formats = self::formats();
        $type = (string) $request->getParam('document_type', 'td1');
        $type = isset($formats[$type]) ? $type : 'td1';
        $segments = $request->getParam('segments');
        $entries = is_array($segments) && is_array($segments[$type] ?? null) ? $segments[$type] : [];

        $typed = false;
        foreach (MrzLayout::segments($formats[$type]) as $index => $segment) {
            if (!$segment->filler && trim((string) ($entries[$index] ?? '')) !== '') {
                $typed = true;
            }
        }

        return [
            'type' => $type,
            'mrz' => $typed ? MrzLayout::compose($formats[$type], array_map('strval', $entries)) : null,
        ];
    }

    /**
     * A message for the first error of a rejected MRZ.
     *
     * @param MrzResult $result
     * @return Phrase
     */
    public function errorMessage(MrzResult $result): Phrase
    {
        $error = $result->getErrors()[0] ?? MrzError::UnknownFormat;

        return match ($error) {
            MrzError::UnknownFormat => __(
                'The code is incomplete or not recognised. Fill in every blank exactly as printed on your document.'
            ),
            MrzError::InvalidCharacters => __(
                'The code contains characters that are not allowed. Use only letters, digits and "<".'
            ),
            MrzError::DocumentExpired => __('This document has expired. Please use a valid ID card or passport.'),
            default => __('The code could not be verified. Please check it for typing mistakes.'),
        };
    }
}
