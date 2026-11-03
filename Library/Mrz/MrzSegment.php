<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * One blank of the guided MRZ input: a stretch of characters on one line.
 */
final class MrzSegment {

  /**
   * Constructs a segment.
   *
   * @param int $line
   *   The line of the MRZ, starting at 0.
   * @param int $start
   *   The position of the first character on the line, starting at 0.
   * @param int $length
   *   The number of characters.
   * @param bool $filler
   *   Whether the segment is usually only filler ("<"). Such a segment is
   *   pre-filled, and an empty entry is read as filler. The customer can still
   *   overwrite it for a document that has data there.
   */
  public function __construct(
    public readonly int $line,
    public readonly int $start,
    public readonly int $length,
    public readonly bool $filler,
  ) {}

  /**
   * Returns the value the input starts with.
   */
  public function defaultValue(): string {
    return $this->filler ? str_repeat('<', $this->length) : '';
  }

}
