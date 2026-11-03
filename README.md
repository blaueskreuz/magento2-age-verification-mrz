# Age Verification (MRZ) for Magento 2 / Adobe Commerce

Verifies the age of customers before they can order age-restricted products,
from the machine-readable zone (MRZ) of an ID card or passport.

The customer picks the type of document and fills in the blanks of a card that
looks like the back of it: the characters of the MRZ, with the usual filler
(`<`) already in place and the name line left out. The module checks the
entry, works out the age and discards it. **The MRZ is never stored or
logged.** Only the resulting age, the date of the check and how it was done
are saved.

Module name: `BlauesKreuz_AgeVerificationMrz`
Composer package: `blaueskreuz/module-age-verification-mrz`

## Requirements

- Magento Open Source or Adobe Commerce 2.4.8
- PHP 8.3 or 8.4

Developed and tested on Magento Open Source 2.4.8-p5 with PHP 8.3, on the Hyvä
theme (Hyvä Theme Module 1.4.5) and on Luma. The age page is plain HTML,
CSS and JavaScript. It does not need Knockout (Luma), Alpine or Tailwind
(Hyvä), so it works with both. The Hyvä default theme has no checkout of its
own; use Hyvä Checkout, the Luma Fallback Checkout or another checkout.

## How it works

- Supports the MRZ formats TD1 (3 x 30), TD2 (2 x 36) and TD3 (2 x 44).
- Checks all check digits, that the dates exist and that the document has not
  expired, and calculates the age from the date of birth.
- Opening the checkout with a cart that needs an age check sends the customer
  to the page "Verify your age" (`/agecheck/verify`).
- **The check is enforced on the server.** Placing an order is refused when
  the age is missing, however the order is placed through the shop's order
  placement (tested with the checkout and the REST API). Orders created in the admin by staff are not checked.
- Registered customers verify once. The age and date are saved on their
  account, and they are not asked again for products up to that age. A stored
  age counts for the years that have passed since (age 17 verified a year ago
  proves 18 today).
- Guests verify on every order. The result is saved on the cart and the order
  only.
- Optionally, logged-in customers can verify in their account too (page "Age
  verification" in the customer account), so they are not asked at the
  checkout. It is off by default; see Configuration.
- Staff can set or clear the verified age of a customer in the customer form of
  the admin (field "Verified age" under "Account Information"). It is marked
  "set by staff", dated now, and only users with the permission "Set the
  verified age of customers" can change it; for others the field is read-only.
- A stored age is only replaced by one that is at least as strong, so a younger
  specimen entered later never lowers the age of an account.
- Customer groups can be exempted in the settings; their orders record that
  they were exempt.
- If the cart contains several products, the highest age applies.

## Installation

```
composer require blaueskreuz/module-age-verification-mrz
bin/magento module:enable BlauesKreuz_AgeVerificationMrz
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento cache:flush
```

The module adds columns to the tables `quote` and `sales_order`, a product
attribute `mrz_min_age` and three customer attributes (`mrz_verified_age`,
`mrz_verified_date`, `mrz_verified_method`).

## Configuration

Stores > Configuration > Sales > Age Verification (MRZ):

1. **Enable age verification.**
2. **Default minimum age.** The default is 0, so that only products that set a
   minimum age are checked.
3. **Available minimum ages**, for example `16, 18, 21`.
4. **Let customers verify their age in their account** (off by default): adds
   the page "Age verification" to the customer account. When off, customers are
   only asked before the checkout.
5. **Customer groups that are never asked.**
6. **Show youth-protection message** (on by default) and its text. Leave the
   text empty to use the default, "Together for youth protection!", which is
   shown in the language of the customer (English, German, French and Italian
   are included). A text you enter is shown as it is, in every language. It can
   be set per store view, so that a multilingual shop can word it per language.
7. Optionally set the attribute **Minimum age** on a product. The list offers
   the shop default, "No age check" and the available ages. It overrides the
   default, whether higher or lower. For a configurable product, the chosen
   variation overrides its parent.

Permissions (System > Permissions > User Roles): the settings, viewing the
evidence of orders (and the report) and setting the verified age of customers
have their own resources. Give them to the roles that need them.

## Evidence for the person in charge

Every order with age-restricted products keeps its own record, written when the
order is placed:

- the highest age its products needed;
- the age the customer was verified for, when, and **how**: at this checkout,
  at an earlier checkout (saved on the account), set by staff, or exempt because
  of the customer group.

It is shown in the section "Age verification" on the order page in the admin,
and in a report at Reports > Sales > Age verification, for users with the
resource "View the age verification evidence of orders". The report lists the
orders with age-restricted products, can be filtered by period and by "only
orders without enough evidence", and has a "Download CSV" button for
spreadsheets (cells that a spreadsheet would read as a formula are protected
with a leading apostrophe). An order shows "No evidence" if nothing enough was
recorded, also for customers who were verified earlier and were not asked
again. The evidence holds ages, dates and the method, and nothing from the document. It
shows that a code consistent with the age was entered at that time, not that
the person held the document.

## Privacy and legal

What the module stores, on the cart and order and, for registered customers, on
their account: the age in full years, the date of the check and how it was
done. On orders it also stores the age the products needed. What it never
stores or logs: the MRZ, the name, the document number and the date of birth.

An age that belongs to a person is personal data, so data protection law applies
to the shop, for example the Swiss Federal Act on Data Protection and, for
customers in the EU, the GDPR. This module is a tool and does not make a shop
compliant by itself. The operator is responsible for a legal basis for the
check, for naming the age check in the privacy policy, for deciding how long ages
are kept (in particular on guest orders) and for handling requests for access
and deletion. Age limits differ by country and by product. This is not legal
advice.

## Limitations

- The check cannot tell a real document from a made-up one. The check digits of
  an MRZ follow a public rule, so anyone can compute a valid-looking MRZ, and a
  customer can enter an MRZ they have only seen. The module proves that the entry
  is consistent and that the document has not expired, not that the customer
  holds the document. Treat it as due diligence, and for products with a high
  risk also check the age when the goods are handed over.
- TD1 document numbers longer than 9 characters and documents without an expiry
  date are not supported.
- Orders created by staff in the admin are not checked.
- A guest who creates an account at the end of the checkout does not take the
  age over to the account yet; they verify once on their account.

## Translations

`i18n/` has the German (`de_CH`), French (`fr_CH`) and Italian (`it_CH`)
translations. English is the source language.

## Tests

```
vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist \
  app/code/BlauesKreuz/AgeVerificationMrz/Test/Unit
```

## Support

Perspectives for life – your donation to the Blue Cross makes them possible:
support our addiction work with a donation – thank you!
<https://blaueskreuz.ch/spende>

Perspektiven für's Leben – Ihre Spende für das Blaue Kreuz macht sie möglich:
Unterstützen Sie unsere Suchtarbeit mit einer Spende – merci!
<https://blaueskreuz.ch/spende>

The call is shown on the settings page of the module and here. It is never
shown to the customers of your shop.

## Licence

GPL-2.0-or-later.
