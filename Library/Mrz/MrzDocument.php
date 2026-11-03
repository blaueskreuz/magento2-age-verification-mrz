<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * The raw fields of a parsed MRZ.
 *
 * This holds personal data (the document number). It is only meant to live for
 * the duration of a validation call and must never be stored, logged or
 * returned to callers outside the library; MrzResult deliberately contains
 * none of it.
 */
final class MrzDocument {

  /**
   * Constructs a document from the parsed fields.
   *
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat $format
   *   The detected format.
   * @param string $documentNumber
   *   The document number field (9 characters).
   * @param string $documentNumberCheck
   *   Its check digit character.
   * @param string $birthDate
   *   Birth date as YYMMDD.
   * @param string $birthDateCheck
   *   Its check digit character.
   * @param string $expiryDate
   *   Expiry date as YYMMDD.
   * @param string $expiryDateCheck
   *   Its check digit character.
   * @param string|null $optionalData
   *   The optional data field that has its own check digit (TD3 only).
   * @param string|null $optionalDataCheck
   *   Its check digit character (TD3 only).
   * @param string $compositeData
   *   The characters covered by the composite check digit.
   * @param string $compositeCheck
   *   The composite check digit character.
   */
  public function __construct(
    public readonly MrzFormat $format,
    public readonly string $documentNumber,
    public readonly string $documentNumberCheck,
    public readonly string $birthDate,
    public readonly string $birthDateCheck,
    public readonly string $expiryDate,
    public readonly string $expiryDateCheck,
    public readonly ?string $optionalData,
    public readonly ?string $optionalDataCheck,
    public readonly string $compositeData,
    public readonly string $compositeCheck,
  ) {}

  /**
   * Hides the personal data from var_dump() and similar output.
   */
  public function __debugInfo(): array {
    return ['format' => $this->format];
  }

}
