<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Library\Mrz;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzParseException;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests MRZ format detection, normalisation and field extraction.
 *
 */
class MrzParserTest extends TestCase {

  /**
   */
  #[DataProvider('formatProvider')]
  public function testFormatDetection(string $mrz, MrzFormat $format): void {
    $this->assertSame($format, (new MrzParser())->parse($mrz)->format);
  }

  /**
   * Provides data for the test of the same name.
   *
   * @return array
   *   Test cases.
   */
  public static function formatProvider(): array {
    return [
      'TD1' => [MrzSamples::TD1, MrzFormat::TD1],
      'TD2' => [MrzSamples::TD2, MrzFormat::TD2],
      'TD3' => [MrzSamples::TD3, MrzFormat::TD3],
    ];
  }

  /**
   */
  #[DataProvider('fieldsProvider')]
  public function testFieldExtraction(string $mrz, string $documentNumber): void {
    $document = (new MrzParser())->parse($mrz);
    $this->assertSame($documentNumber, $document->documentNumber);
    $this->assertSame('740812', $document->birthDate);
    $this->assertSame('2', $document->birthDateCheck);
    $this->assertSame('120415', $document->expiryDate);
    $this->assertSame('9', $document->expiryDateCheck);
  }

  /**
   * Provides data for the test of the same name.
   *
   * @return array
   *   Test cases.
   */
  public static function fieldsProvider(): array {
    return [
      'TD1' => [MrzSamples::TD1, 'D23145890'],
      'TD2' => [MrzSamples::TD2, 'D23145890'],
      'TD3' => [MrzSamples::TD3, 'L898902C3'],
    ];
  }

  /**
   */
  public function testTd3OptionalDataFields(): void {
    $document = (new MrzParser())->parse(MrzSamples::TD3);
    $this->assertSame('ZE184226B<<<<<', $document->optionalData);
    $this->assertSame('1', $document->optionalDataCheck);
    $this->assertSame('0', $document->compositeCheck);
  }

  /**
   */
  public function testTd1And2HaveNoSeparateOptionalDataCheck(): void {
    $this->assertNull((new MrzParser())->parse(MrzSamples::TD1)->optionalData);
    $this->assertNull((new MrzParser())->parse(MrzSamples::TD2)->optionalData);
  }

  /**
   */
  #[DataProvider('normalisationProvider')]
  public function testNormalisation(string $input): void {
    $this->assertSame(MrzFormat::TD3, (new MrzParser())->parse($input)->format);
  }

  /**
   * Provides data for the test of the same name.
   *
   * @return array
   *   Test cases.
   */
  public static function normalisationProvider(): array {
    $lines = explode("\n", MrzSamples::TD3);
    return [
      'lowercase' => [strtolower(MrzSamples::TD3)],
      'CRLF' => [str_replace("\n", "\r\n", MrzSamples::TD3)],
      'surrounding whitespace' => ["  \n" . MrzSamples::TD3 . "\n\n  "],
      'single line, no line break' => [$lines[0] . $lines[1]],
      'spaces typed instead of filler' => [str_replace('<', ' ', $lines[0]) . "\n" . $lines[1]],
    ];
  }

  /**
   */
  #[DataProvider('invalidProvider')]
  public function testInvalidInput(string $input, MrzError $error): void {
    try {
      (new MrzParser())->parse($input);
      $this->fail('Expected MrzParseException.');
    }
    catch (MrzParseException $e) {
      $this->assertSame($error, $e->getError());
    }
  }

  /**
   * Provides data for the test of the same name.
   *
   * @return array
   *   Test cases.
   */
  public static function invalidProvider(): array {
    $lines = explode("\n", MrzSamples::TD3);
    return [
      'empty' => ['', MrzError::UnknownFormat],
      'only whitespace' => ["\n  \n", MrzError::UnknownFormat],
      'one line too short' => [$lines[1], MrzError::UnknownFormat],
      'TD3 line truncated' => [$lines[0] . "\n" . substr($lines[1], 0, 43), MrzError::UnknownFormat],
      'TD3 line too long' => [$lines[0] . "\n" . $lines[1] . '<', MrzError::UnknownFormat],
      'four lines' => [MrzSamples::TD1 . "\nABC", MrzError::UnknownFormat],
      'illegal character' => [str_replace('ERIKSSON', 'ERIKSS?N', MrzSamples::TD3), MrzError::InvalidCharacters],
      'umlaut' => [str_replace('ERIKSSON', 'ERIKSSÖN', MrzSamples::TD3), MrzError::InvalidCharacters],
    ];
  }

}
