<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Library\Mrz;

/**
 * Validates an MRZ and derives the holder's age from it.
 *
 * Framework-agnostic: no dependencies besides PHP itself.
 *
 * The MRZ passed to validate() is never stored or logged, and not part of the
 * returned MrzResult.
 */
final class MrzValidator {

  /**
   * How far in the future an expiry year may lie.
   *
   * MRZ dates have two-digit years. An expiry date is read as the latest year
   * that is not more than this many years after today, so a document that
   * expired in "99" is read as 1999, not 2099.
   */
  private const EXPIRY_WINDOW_YEARS = 30;

  /**
   * Validates MRZ text.
   *
   * @param string $mrz
   *   The MRZ as typed or pasted by the customer.
   * @param \DateTimeImmutable|null $now
   *   The reference time, in the time zone the shop operates in. Defaults to
   *   the current time; pass a value for deterministic tests.
   */
  public function validate(string $mrz, ?\DateTimeImmutable $now = NULL): MrzResult {
    $today = ($now ?? new \DateTimeImmutable('now'))->setTime(0, 0);

    try {
      $document = (new MrzParser())->parse($mrz);
    }
    catch (MrzParseException $e) {
      return MrzResult::invalid([$e->getError()]);
    }

    $errors = $this->checkDigitErrors($document);

    $birth = $this->resolveBirthDate($document->birthDate, $today);
    if ($birth === NULL) {
      $errors[] = MrzError::InvalidBirthDate;
    }

    $expiry = $this->resolveExpiryDate($document->expiryDate, $today);
    if ($expiry === NULL) {
      $errors[] = MrzError::InvalidExpiryDate;
    }
    elseif ($expiry < $today) {
      $errors[] = MrzError::DocumentExpired;
    }

    // Without errors the birth date is a real date; the check keeps it so.
    if ($errors !== [] || $birth === NULL) {
      return MrzResult::invalid($errors, $document->format);
    }
    return MrzResult::valid($document->format, $birth->diff($today)->y);
  }

  /**
   * Verifies all check digits.
   *
   * @return \BlauesKreuz\AgeVerificationMrz\Library\Mrz\MrzError[]
   *   The failed checks, in field order.
   */
  protected function checkDigitErrors(MrzDocument $document): array {
    $errors = [];
    if (!CheckDigit::verify($document->documentNumber, $document->documentNumberCheck)) {
      $errors[] = MrzError::DocumentNumberCheck;
    }
    if (!CheckDigit::verify($document->birthDate, $document->birthDateCheck)) {
      $errors[] = MrzError::BirthDateCheck;
    }
    if (!CheckDigit::verify($document->expiryDate, $document->expiryDateCheck)) {
      $errors[] = MrzError::ExpiryDateCheck;
    }
    if ($document->optionalData !== NULL && !$this->optionalDataIsValid($document)) {
      $errors[] = MrzError::OptionalDataCheck;
    }
    if (!CheckDigit::verify($document->compositeData, $document->compositeCheck)) {
      $errors[] = MrzError::CompositeCheck;
    }
    return $errors;
  }

  /**
   * Verifies the optional data check digit.
   *
   * An empty field may carry "<" instead of "0" as its check digit.
   */
  protected function optionalDataIsValid(MrzDocument $document): bool {
    $data = $document->optionalData;
    $check = $document->optionalDataCheck;
    if ($data === NULL || $check === NULL) {
      // The format has no separate check digit for it.
      return TRUE;
    }
    if (trim($data, '<') === '' && $check === '<') {
      return TRUE;
    }
    return CheckDigit::verify($data, $check);
  }

  /**
   * Resolves a YYMMDD birth date to a full date.
   *
   * The year is read as 20YY unless that would lie in the future, then 19YY.
   * This is only wrong for people aged 100 or more.
   *
   * @return \DateTimeImmutable|null
   *   The date at midnight in the time zone of $today, or NULL if it is not a
   *   real calendar date.
   */
  protected function resolveBirthDate(string $yymmdd, \DateTimeImmutable $today): ?\DateTimeImmutable {
    if (!preg_match('/^(\d\d)(\d\d)(\d\d)$/', $yymmdd, $matches)) {
      return NULL;
    }
    [, $yy, $month, $day] = array_map('intval', $matches);
    $year = intdiv((int) $today->format('Y'), 100) * 100 + $yy;
    if ($this->compact($year, $month, $day) > (int) $today->format('Ymd')) {
      $year -= 100;
    }
    return $this->date($year, $month, $day, $today);
  }

  /**
   * Resolves a YYMMDD expiry date to a full date.
   *
   * @return \DateTimeImmutable|null
   *   The date at midnight in the time zone of $today, or NULL if it is not a
   *   real calendar date.
   */
  protected function resolveExpiryDate(string $yymmdd, \DateTimeImmutable $today): ?\DateTimeImmutable {
    if (!preg_match('/^(\d\d)(\d\d)(\d\d)$/', $yymmdd, $matches)) {
      return NULL;
    }
    [, $yy, $month, $day] = array_map('intval', $matches);
    $year = intdiv((int) $today->format('Y'), 100) * 100 + $yy;
    $limit = (int) $today->format('Ymd') + self::EXPIRY_WINDOW_YEARS * 10000;
    while ($this->compact($year, $month, $day) > $limit) {
      $year -= 100;
    }
    return $this->date($year, $month, $day, $today);
  }

  /**
   * Builds a date, or returns NULL if it does not exist in the calendar.
   */
  protected function date(int $year, int $month, int $day, \DateTimeImmutable $reference): ?\DateTimeImmutable {
    if ($year < 1 || !checkdate($month, $day, $year)) {
      return NULL;
    }
    return $reference->setDate($year, $month, $day);
  }

  /**
   * Returns a date as comparable YYYYMMDD integer.
   */
  protected function compact(int $year, int $month, int $day): int {
    return $year * 10000 + $month * 100 + $day;
  }

}
