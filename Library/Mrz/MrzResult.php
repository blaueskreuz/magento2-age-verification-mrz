<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * The outcome of an MRZ validation.
 *
 * This is the only thing that leaves the library. It holds the detected
 * format, the age and error codes, and nothing from the MRZ itself, so it is
 * safe to store, log and serialize.
 */
final class MrzResult {

  /**
   * Constructs a result; use the static factories instead.
   *
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat|null $format
   *   The detected format, if any.
   * @param int|null $age
   *   The age in full years at validation time, only set for valid results.
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError[] $errors
   *   The reasons for rejection; empty for valid results.
   */
  private function __construct(
    private readonly ?MrzFormat $format,
    private readonly ?int $age,
    private readonly array $errors,
  ) {}

  /**
   * Creates the result of a valid document.
   *
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat $format
   *   The detected format.
   * @param int $age
   *   The age in full years.
   */
  public static function valid(MrzFormat $format, int $age): self {
    return new self($format, $age, []);
  }

  /**
   * Creates the result of a rejected document.
   *
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError[] $errors
   *   The reasons, at least one.
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat|null $format
   *   The detected format, if any.
   */
  public static function invalid(array $errors, ?MrzFormat $format = NULL): self {
    return new self($format, NULL, array_values($errors));
  }

  /**
   * Whether the MRZ is well-formed, consistent and the document not expired.
   */
  public function isValid(): bool {
    return $this->errors === [];
  }

  /**
   * Returns the age in full years, or NULL if the MRZ is not valid.
   */
  public function getAge(): ?int {
    return $this->age;
  }

  /**
   * Returns the detected format, or NULL if it could not be determined.
   */
  public function getFormat(): ?MrzFormat {
    return $this->format;
  }

  /**
   * Returns the error codes.
   *
   * @return \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError[]
   *   The errors; empty if the MRZ is valid.
   */
  public function getErrors(): array {
    return $this->errors;
  }

  /**
   * Whether the MRZ is valid and the holder is at least the given age.
   */
  public function meetsMinimumAge(int $minimumAge): bool {
    return $this->isValid() && $this->age >= $minimumAge;
  }

}
