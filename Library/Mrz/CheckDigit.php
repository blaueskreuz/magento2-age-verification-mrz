<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * Calculates MRZ check digits (ICAO Doc 9303 part 3, weights 7-3-1).
 */
final class CheckDigit {

  private const WEIGHTS = [7, 3, 1];

  /**
   * Computes the check digit of a string of MRZ characters.
   *
   * Digits count as themselves, A-Z as 10-35 and the filler "<" as 0.
   *
   * @param string $data
   *   Characters from [0-9A-Z<].
   *
   * @return int
   *   The check digit, 0-9.
   *
   * @throws \InvalidArgumentException
   *   If the data contains other characters.
   */
  public static function compute(string $data): int {
    // Before PHP 8.2 str_split() returns [''] for an empty string.
    if ($data === '') {
      return 0;
    }
    $sum = 0;
    foreach (str_split($data) as $position => $character) {
      $sum += self::value($character) * self::WEIGHTS[$position % 3];
    }
    return $sum % 10;
  }

  /**
   * Checks a check digit character against the data it protects.
   *
   * @param string $data
   *   The protected characters.
   * @param string $check
   *   The check digit as found in the MRZ (one character).
   */
  public static function verify(string $data, string $check): bool {
    if (strlen($check) !== 1 || !ctype_digit($check)) {
      return FALSE;
    }
    return self::compute($data) === (int) $check;
  }

  /**
   * Returns the numeric value of an MRZ character.
   */
  private static function value(string $character): int {
    if ($character === '<') {
      return 0;
    }
    if ($character >= '0' && $character <= '9') {
      return (int) $character;
    }
    if ($character >= 'A' && $character <= 'Z') {
      return ord($character) - 55;
    }
    throw new \InvalidArgumentException('Invalid MRZ character.');
  }

}
