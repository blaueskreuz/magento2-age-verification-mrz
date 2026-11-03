<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Library\Mrz;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\CheckDigit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ICAO 9303 check digit calculation.
 *
 */
class CheckDigitTest extends TestCase {

  /**
   */
  #[DataProvider('computeProvider')]
  public function testCompute(string $data, int $expected): void {
    $this->assertSame($expected, CheckDigit::compute($data));
  }

  /**
   * Provides data for the test of the same name.
   *
   * @return array
   *   Test cases.
   */
  public static function computeProvider(): array {
    return [
      'ICAO document number' => ['L898902C3', 6],
      'ICAO date of birth' => ['740812', 2],
      'ICAO date of expiry' => ['120415', 9],
      'ICAO personal number' => ['ZE184226B<<<<<', 1],
      'filler only' => ['<<<<<<', 0],
      'empty string' => ['', 0],
      'letters count 10 to 35' => ['AZ', 5],
    ];
  }

  /**
   */
  public function testVerify(): void {
    $this->assertTrue(CheckDigit::verify('740812', '2'));
    $this->assertFalse(CheckDigit::verify('740812', '3'));
    $this->assertFalse(CheckDigit::verify('740812', '<'));
    $this->assertFalse(CheckDigit::verify('740812', 'A'));
    $this->assertFalse(CheckDigit::verify('740812', ''));
  }

}
