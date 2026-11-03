<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Library\Mrz;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\VerifiedAge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the lower bound of an age that was verified in the past.
 *
 */
class VerifiedAgeTest extends TestCase {

  /**
   */
  #[DataProvider('atLeastProvider')]
  public function testAtLeast(int $age, string $verifiedOn, string $now, int $expected): void {
    $utc = new \DateTimeZone('UTC');
    $this->assertSame($expected, VerifiedAge::atLeast(
      $age,
      new \DateTimeImmutable($verifiedOn, $utc),
      new \DateTimeImmutable($now, $utc),
    ));
  }

  /**
   * Provides data for ::testAtLeast.
   *
   * @return array
   *   Test cases: age, verification date, current time, expected age.
   */
  public static function atLeastProvider(): array {
    return [
      'same day' => [17, '2026-03-10 09:00', '2026-03-10 18:30', 17],
      'a day later' => [17, '2026-03-10', '2026-03-11', 17],
      'one day short of a year' => [17, '2026-03-10', '2027-03-09', 17],
      'exactly one year' => [17, '2026-03-10', '2027-03-10', 18],
      'time of day is ignored' => [17, '2026-03-10 20:00', '2027-03-10 01:00', 18],
      'several years' => [16, '2020-01-01', '2026-10-13', 22],
      'leap day verification' => [17, '2024-02-29', '2025-02-28', 17],
      'leap day verification, 1 March' => [17, '2024-02-29', '2025-03-01', 18],
      'verification date in the future' => [17, '2027-01-01', '2026-01-01', 17],
    ];
  }

}
