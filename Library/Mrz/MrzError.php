<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * Reasons why an MRZ is rejected.
 *
 * The cases carry no user data, so they are safe to log or display. The
 * integrating application maps them to translated messages.
 */
enum MrzError: string {

  case UnknownFormat = 'unknown_format';
  case InvalidCharacters = 'invalid_characters';
  case DocumentNumberCheck = 'document_number_check';
  case BirthDateCheck = 'birth_date_check';
  case ExpiryDateCheck = 'expiry_date_check';
  case OptionalDataCheck = 'optional_data_check';
  case CompositeCheck = 'composite_check';
  case InvalidBirthDate = 'invalid_birth_date';
  case InvalidExpiryDate = 'invalid_expiry_date';
  case DocumentExpired = 'document_expired';

}
