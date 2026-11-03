<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * Works with ages that were verified at some point in the past.
 */
final class VerifiedAge {

  /**
   * Returns the age a person is at least, given an earlier verified age.
   *
   * A person who was verified to be $age years old on $verifiedOn is at least
   * $age plus the full years that have passed since. It is a lower bound, as
   * the stored age is not their exact age: they may have had a birthday since.
   *
   * @param int $age
   *   The age in full years when it was verified.
   * @param \DateTimeImmutable $verifiedOn
   *   When it was verified.
   * @param \DateTimeImmutable $now
   *   The current time.
   *
   * @return int
   *   The minimum age today.
   */
  public static function atLeast(int $age, \DateTimeImmutable $verifiedOn, \DateTimeImmutable $now): int {
    $difference = $verifiedOn->setTime(0, 0)->diff($now->setTime(0, 0));
    if ($difference->invert) {
      return $age;
    }
    return $age + $difference->y;
  }

}
