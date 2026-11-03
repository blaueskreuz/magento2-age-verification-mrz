<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Library\Mrz;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests MRZ validation: check digits, expiry, birth date and age.
 *
 */
class MrzValidatorTest extends TestCase {

  /**
   * Builds a date-only immutable date.
   */
  protected static function day(string $date): \DateTimeImmutable {
    return new \DateTimeImmutable($date . ' 00:00:00', new \DateTimeZone('UTC'));
  }

  /**
   *
   * @param string $mrz
   *   The MRZ.
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat $format
   *   The expected format.
   * @param string $now
   *   The reference date.
   * @param int $age
   *   The expected age.
   */
  #[DataProvider('validProvider')]
  public function testValidDocuments(string $mrz, MrzFormat $format, string $now, int $age): void {
    $result = (new MrzValidator())->validate($mrz, self::day($now));
    $this->assertSame([], $result->getErrors());
    $this->assertTrue($result->isValid());
    $this->assertSame($format, $result->getFormat());
    $this->assertSame($age, $result->getAge());
  }

  /**
   * Provides data for the test of the same name.
   *
   * @return array
   *   Test cases.
   */
  public static function validProvider(): array {
    return [
      'TD1 specimen' => [MrzSamples::TD1, MrzFormat::TD1, '2010-06-01', 35],
      'TD2 specimen' => [MrzSamples::TD2, MrzFormat::TD2, '2010-06-01', 35],
      'TD3 specimen' => [MrzSamples::TD3, MrzFormat::TD3, '2010-06-01', 35],
      'lowercase and spaces for filler' => [
        strtolower(str_replace('<', ' ', MrzSamples::TD1)),
        MrzFormat::TD1,
        '2010-06-01',
        35,
      ],
      'birthday today' => [MrzSamples::TD3, MrzFormat::TD3, '2010-08-12', 36],
      'day before birthday' => [MrzSamples::TD3, MrzFormat::TD3, '2010-08-11', 35],
      'expires today' => [MrzSamples::TD3, MrzFormat::TD3, '2012-04-15', 37],
      'born 2010 resolves to 20YY' => [MrzSamples::TD3_BORN_2010, MrzFormat::TD3, '2026-10-13', 16],
      'born 1999 resolves to 19YY' => [MrzSamples::TD3_BORN_1999, MrzFormat::TD3, '2026-10-13', 26],
      'born 2000 on a leap day' => [MrzSamples::TD3_BORN_LEAP_2000, MrzFormat::TD3, '2026-10-13', 26],
      'leap day birthday, non-leap year, day before' => [
        MrzSamples::TD3_BORN_LEAP_2000,
        MrzFormat::TD3,
        '2025-02-28',
        24,
      ],
      'leap day birthday, non-leap year, on 1 March' => [
        MrzSamples::TD3_BORN_LEAP_2000,
        MrzFormat::TD3,
        '2025-03-01',
        25,
      ],
    ];
  }

  /**
   */
  public function testSeventeenToEighteenBoundary(): void {
    // Born 2010-06-15.
    $validator = new MrzValidator();
    $before = $validator->validate(MrzSamples::TD3_BORN_2010, self::day('2028-06-14'));
    $on = $validator->validate(MrzSamples::TD3_BORN_2010, self::day('2028-06-15'));
    $this->assertSame(17, $before->getAge());
    $this->assertSame(18, $on->getAge());
    $this->assertFalse($before->meetsMinimumAge(18));
    $this->assertTrue($on->meetsMinimumAge(18));
  }

  /**
   */
  public function testSameDigitsResolveToDifferentCenturies(): void {
    // A "10" birth year is 2010 in 2026, but 1910 in 2005.
    $result = (new MrzValidator())->validate(MrzSamples::TD3_BORN_2010, self::day('2005-01-01'));
    $this->assertTrue($result->isValid());
    $this->assertSame(94, $result->getAge());
  }

  /**
   *
   * @param string $mrz
   *   The MRZ.
   * @param string $now
   *   The reference date.
   * @param \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError[] $errors
   *   The expected errors.
   */
  #[DataProvider('invalidProvider')]
  public function testInvalidDocuments(string $mrz, string $now, array $errors): void {
    $result = (new MrzValidator())->validate($mrz, self::day($now));
    $this->assertFalse($result->isValid());
    $this->assertEquals($errors, $result->getErrors());
    $this->assertFalse($result->meetsMinimumAge(0));
  }

  /**
   * Provides data for the test of the same name.
   *
   * @return array
   *   Test cases.
   */
  public static function invalidProvider(): array {
    $td3_lines = explode("\n", MrzSamples::TD3);
    $td3_line2 = $td3_lines[1];
    $swap = fn (string $mrz, string $from, string $to): string => str_replace($from, $to, $mrz);
    return [
      'unknown format' => ['not an mrz', '2010-06-01', [MrzError::UnknownFormat]],
      'illegal characters' => [
        $swap(MrzSamples::TD3, 'ERIKSSON', 'ERIKSS?N'),
        '2010-06-01',
        [MrzError::InvalidCharacters],
      ],
      'TD3 wrong document number check digit' => [
        $swap(MrzSamples::TD3, 'L898902C36', 'L898902C37'),
        '2010-06-01',
        [MrzError::DocumentNumberCheck, MrzError::CompositeCheck],
      ],
      'TD3 wrong birth date check digit' => [
        $swap(MrzSamples::TD3, '7408122F', '7408123F'),
        '2010-06-01',
        [MrzError::BirthDateCheck, MrzError::CompositeCheck],
      ],
      'TD3 wrong expiry check digit' => [
        $swap(MrzSamples::TD3, '1204159ZE', '1204158ZE'),
        '2010-06-01',
        [MrzError::ExpiryDateCheck, MrzError::CompositeCheck],
      ],
      'TD3 wrong optional data check digit' => [
        $swap(MrzSamples::TD3, 'B<<<<<10', 'B<<<<<20'),
        '2010-06-01',
        [MrzError::OptionalDataCheck, MrzError::CompositeCheck],
      ],
      'TD3 wrong composite check digit' => [
        $swap(MrzSamples::TD3, 'B<<<<<10', 'B<<<<<11'),
        '2010-06-01',
        [MrzError::CompositeCheck],
      ],
      'TD3 tampered birth date, digits unchanged' => [
        $swap(MrzSamples::TD3, 'UTO7408122F', 'UTO8008122F'),
        '2010-06-01',
        [MrzError::BirthDateCheck, MrzError::CompositeCheck],
      ],
      'TD1 wrong document number check digit' => [
        $swap(MrzSamples::TD1, 'D231458907', 'D231458908'),
        '2010-06-01',
        [MrzError::DocumentNumberCheck, MrzError::CompositeCheck],
      ],
      'TD1 wrong composite check digit' => [
        $swap(MrzSamples::TD1, '<<<<<<<<<<<6', '<<<<<<<<<<<5'),
        '2010-06-01',
        [MrzError::CompositeCheck],
      ],
      'TD2 wrong expiry check digit' => [
        $swap(MrzSamples::TD2, '1204159<', '1204158<'),
        '2010-06-01',
        [MrzError::ExpiryDateCheck, MrzError::CompositeCheck],
      ],
      'TD2 wrong composite check digit' => [
        $swap(MrzSamples::TD2, '<<<<<<<6', '<<<<<<<7'),
        '2010-06-01',
        [MrzError::CompositeCheck],
      ],
      'expired yesterday' => [MrzSamples::TD3, '2012-04-16', [MrzError::DocumentExpired]],
      'expired long ago' => [MrzSamples::TD3, '2026-10-13', [MrzError::DocumentExpired]],
      'expiry 99 read as 1999' => [MrzSamples::TD3_EXPIRY_99, '2026-10-13', [MrzError::DocumentExpired]],
      'birth date 30 February' => [MrzSamples::TD3_BAD_DAY, '2010-06-01', [MrzError::InvalidBirthDate]],
      'birth date month 13' => [MrzSamples::TD3_BAD_MONTH, '2010-06-01', [MrzError::InvalidBirthDate]],
      'TD3 line 2 only' => [$td3_line2, '2010-06-01', [MrzError::UnknownFormat]],
    ];
  }

  /**
   */
  #[DataProvider('emptyOptionalDataProvider')]
  public function testEmptyOptionalDataMayUseZeroOrFillerAsCheckDigit(string $mrz): void {
    // ICAO allows "<" instead of "0" as the check digit of an empty field.
    $result = (new MrzValidator())->validate($mrz, self::day('2010-06-01'));
    $this->assertSame([], $result->getErrors());
    $this->assertSame(35, $result->getAge());
  }

  /**
   * Provides data for the test of the same name.
   *
   * @return array
   *   Test cases.
   */
  public static function emptyOptionalDataProvider(): array {
    return [
      'zero' => [MrzSamples::TD3_EMPTY_OPTIONAL_ZERO],
      'filler' => [MrzSamples::TD3_EMPTY_OPTIONAL_FILLER],
    ];
  }

  /**
   */
  public function testResultDoesNotContainTheMrz(): void {
    $result = (new MrzValidator())->validate(MrzSamples::TD3, self::day('2010-06-01'));
    $dump = serialize($result) . var_export($result, TRUE) . json_encode($result->getErrors());
    foreach (['L898902C3', 'ERIKSSON', 'ANNA', '740812', 'ZE184226B'] as $secret) {
      $this->assertStringNotContainsString($secret, $dump);
    }
  }

  /**
   */
  public function testDefaultsToCurrentTime(): void {
    // The specimen has long expired; without an injected clock it must fail.
    $result = (new MrzValidator())->validate(MrzSamples::TD3);
    $this->assertContains(MrzError::DocumentExpired, $result->getErrors());
  }

}
