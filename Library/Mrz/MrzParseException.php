<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * Thrown when the input cannot be read as an MRZ.
 */
class MrzParseException extends \InvalidArgumentException {

  public function __construct(protected readonly MrzError $error) {
    // Only the error code is used as message, never the input.
    parent::__construct($error->value);
  }

  /**
   * Returns the reason for the failure.
   */
  public function getError(): MrzError {
    return $this->error;
  }

}
