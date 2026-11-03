<?php

declare(strict_types=1);

namespace BlauesKreuz\AgeVerificationMrz\Test\Unit\Library\Mrz;

/**
 * Sample MRZs for the unit tests.
 *
 * TD1, TD2 and TD3 are the specimens from ICAO Doc 9303 (fictional person,
 * state "UTO"): born 1974-08-12, document expires 2012-04-15. The others were
 * generated with independently computed check digits and vary one field.
 */
final class MrzSamples {

  public const TD1 = "I<UTOD231458907<<<<<<<<<<<<<<<\n7408122F1204159UTO<<<<<<<<<<<6\nERIKSSON<<ANNA<MARIA<<<<<<<<<<";

  public const TD2 = "I<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<\nD231458907UTO7408122F1204159<<<<<<<6";

  public const TD3 = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO7408122F1204159ZE184226B<<<<<10";

  /**
   * Born 2010-06-15, expires 2030-12-31.
   */
  public const TD3_BORN_2010 = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO1006157F3012316ZE184226B<<<<<16";

  /**
   * Born 2000-02-29 (leap day), expires 2030-12-31.
   */
  public const TD3_BORN_LEAP_2000 = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO0002299F3012316ZE184226B<<<<<16";

  /**
   * Born 1999-12-31, expires 2030-12-31.
   */
  public const TD3_BORN_1999 = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO9912315F3012316ZE184226B<<<<<12";

  /**
   * Birth date 740230 (30 February), check digit is correct.
   */
  public const TD3_BAD_DAY = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO7402304F3012316ZE184226B<<<<<14";

  /**
   * Birth date 741312 (month 13), check digit is correct.
   */
  public const TD3_BAD_MONTH = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO7413128F3012316ZE184226B<<<<<18";

  /**
   * Born 1974-08-12, expiry 991231 (read as 1999, so long expired).
   */
  public const TD3_EXPIRY_99 = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO7408122F9912315ZE184226B<<<<<16";

  /**
   * Empty optional data with "0" as its check digit.
   */
  public const TD3_EMPTY_OPTIONAL_ZERO = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO7408122F1204159<<<<<<<<<<<<<<08";

  /**
   * Empty optional data with "<" as its check digit (also allowed).
   */
  public const TD3_EMPTY_OPTIONAL_FILLER = "P<UTOERIKSSON<<ANNA<MARIA<<<<<<<<<<<<<<<<<<<\nL898902C36UTO7408122F1204159<<<<<<<<<<<<<<<8";

}
