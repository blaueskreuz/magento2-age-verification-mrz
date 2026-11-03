<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Library\Mrz;

use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzFormat;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzLayout;
use BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests the guided input layout: segments and composing the MRZ from them.
 *
 */
class MrzLayoutTest extends TestCase {

  /**
   * Returns a date-only immutable date.
   */
  protected static function day(string $date): \DateTimeImmutable {
    return new \DateTimeImmutable($date . ' 00:00:00', new \DateTimeZone('UTC'));
  }

  /**
   */
  #[DataProvider('formatProvider')]
  public function testSegmentsFillWholeLines(MrzFormat $format): void {
    $lengths = [];
    foreach (MrzLayout::segments($format) as $segment) {
      $lengths[$segment->line] = ($lengths[$segment->line] ?? 0) + $segment->length;
    }
    foreach ($lengths as $line => $length) {
      $this->assertSame($format->lineLength(), $length, "Line $line of {$format->value}.");
    }
  }

  /**
   */
  #[DataProvider('formatProvider')]
  public function testSegmentsAreOrderedAndStartCorrectly(MrzFormat $format): void {
    $previous_line = -1;
    $expected_start = 0;
    foreach (MrzLayout::segments($format) as $segment) {
      if ($segment->line !== $previous_line) {
        $this->assertGreaterThan($previous_line, $segment->line);
        $expected_start = 0;
        $previous_line = $segment->line;
      }
      $this->assertSame($expected_start, $segment->start);
      $expected_start += $segment->length;
    }
  }

  /**
   * Provides the three formats.
   *
   * @return array
   *   Test cases.
   */
  public static function formatProvider(): array {
    return [
      'TD1' => [MrzFormat::TD1],
      'TD2' => [MrzFormat::TD2],
      'TD3' => [MrzFormat::TD3],
    ];
  }

  /**
   */
  public function testTd1Layout(): void {
    $layout = array_map(
      fn ($s) => [$s->line, $s->length, $s->filler],
      MrzLayout::segments(MrzFormat::TD1),
    );
    $this->assertSame([
      [0, 15, FALSE],
      [0, 15, TRUE],
      [1, 18, FALSE],
      [1, 11, TRUE],
      [1, 1, FALSE],
    ], $layout);
  }

  /**
   */
  public function testTd3AndTd2Layout(): void {
    $td3 = array_map(fn ($s) => [$s->line, $s->length, $s->filler], MrzLayout::segments(MrzFormat::TD3));
    $this->assertSame([[1, 28, FALSE], [1, 14, TRUE], [1, 2, FALSE]], $td3);
    $td2 = array_map(fn ($s) => [$s->line, $s->length, $s->filler], MrzLayout::segments(MrzFormat::TD2));
    $this->assertSame([[1, 28, FALSE], [1, 7, TRUE], [1, 1, FALSE]], $td2);
  }

  /**
   */
  public function testFillerSegmentsDefaultToFiller(): void {
    foreach (MrzLayout::segments(MrzFormat::TD1) as $segment) {
      $this->assertSame(
        $segment->filler ? str_repeat('<', $segment->length) : '',
        $segment->defaultValue(),
      );
    }
  }

  /**
   */
  public function testIgnoredLinesAreTheNameLines(): void {
    $this->assertSame([2], MrzLayout::ignoredLines(MrzFormat::TD1));
    $this->assertSame([0], MrzLayout::ignoredLines(MrzFormat::TD2));
    $this->assertSame([0], MrzLayout::ignoredLines(MrzFormat::TD3));
  }

  /**
   */
  #[DataProvider('specimenProvider')]
  public function testComposedMrzIsValid(MrzFormat $format, array $segments): void {
    $mrz = MrzLayout::compose($format, $segments);
    $lines = explode("\n", $mrz);
    $this->assertCount($format->lineCount(), $lines);
    foreach ($lines as $line) {
      $this->assertSame($format->lineLength(), strlen($line));
    }

    $result = (new MrzValidator())->validate($mrz, self::day('2010-06-01'));
    $this->assertSame([], $result->getErrors());
    $this->assertSame(35, $result->getAge());
  }

  /**
   * Provides the ICAO specimens, as a customer would fill in the segments.
   *
   * @return array
   *   Test cases: format, segments.
   */
  public static function specimenProvider(): array {
    return [
      'TD1' => [MrzFormat::TD1, [
        'I<UTOD231458907',
        '<<<<<<<<<<<<<<<',
        '7408122F1204159UTO',
        '<<<<<<<<<<<',
        '6',
      ],
      ],
      'TD2' => [MrzFormat::TD2, [
        'D231458907UTO7408122F1204159',
        '<<<<<<<',
        '6',
      ],
      ],
      'TD3 with personal number' => [MrzFormat::TD3, [
        'L898902C36UTO7408122F1204159',
        'ZE184226B<<<<<',
        '10',
      ],
      ],
    ];
  }

  /**
   */
  public function testNameLinesAreFilledWithFiller(): void {
    $td1 = explode("\n", MrzLayout::compose(MrzFormat::TD1, ['I<UTOD231458907', '', '7408122F1204159UTO', '', '6']));
    $this->assertSame(str_repeat('<', 30), $td1[2]);
    $td3 = explode("\n", MrzLayout::compose(MrzFormat::TD3, ['L898902C36UTO7408122F1204159', '', '10']));
    $this->assertSame(str_repeat('<', 44), $td3[0]);
  }

  /**
   */
  public function testEmptyFillerSegmentsAreFilled(): void {
    $mrz = MrzLayout::compose(MrzFormat::TD3, ['L898902C36UTO7408122F1204159', '', '<0']);
    $this->assertStringContainsString('F1204159' . str_repeat('<', 14) . '<0', $mrz);
    $short = MrzLayout::compose(MrzFormat::TD3, ['L898902C36UTO7408122F1204159', 'ZE18', '10']);
    $this->assertStringContainsString('ZE18' . str_repeat('<', 10) . '10', $short);
  }

  /**
   */
  public function testInputIsNormalised(): void {
    $typed = MrzLayout::compose(MrzFormat::TD3, ['l898902c36uto7408122f1204159', 'ze184226b     ', '10']);
    $clean = MrzLayout::compose(MrzFormat::TD3, ['L898902C36UTO7408122F1204159', 'ZE184226B<<<<<', '10']);
    $this->assertSame($clean, $typed);
  }

  /**
   */
  public function testIncompleteTypedSegmentsAreNotRepaired(): void {
    // A missing character must not be papered over with a filler.
    $mrz = MrzLayout::compose(MrzFormat::TD3, ['L898902C36UTO7408122F120415', '', '10']);
    $result = (new MrzValidator())->validate($mrz, self::day('2010-06-01'));
    $this->assertFalse($result->isValid());
  }

  /**
   */
  public function testMissingSegmentsUseDefaults(): void {
    $mrz = MrzLayout::compose(MrzFormat::TD3, [0 => 'L898902C36UTO7408122F1204159', 2 => '10']);
    $this->assertStringContainsString('F1204159' . str_repeat('<', 14) . '10', $mrz);
  }

}
