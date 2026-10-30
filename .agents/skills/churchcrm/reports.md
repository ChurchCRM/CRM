---
title: "Reports"
intent: "Build a PDF or CSV report with MpdfRenderer, a data service, and a Twig template"
tags: ["reports","pdf","mpdf","finance","twig"]
prereqs: ["[[service-layer]]","[[currency-localization]]","[[i18n-localization]]"]
complexity: "intermediate"
---

# Reports

A report is a service that builds the data, a Twig template under `src/templates/reports/`, and a thin route that calls `MpdfRenderer`.

## Shape

1. The service owns the query, filters, and the array the template prints. Read an existing one (`EnvelopeReportService`, `GivingStatementService`) and copy that shape.
2. The route validates input, calls the service, and streams the PDF.
3. Letter-style reports extend `_letter.html.twig`. Other layouts extend `_base.html.twig` or stand alone when the page is not a letter (labels, stubs).
4. Render with `MpdfRenderer`. It loads the template, injects church metadata, locale, and text direction, and builds the mPDF document.

```php
$pdf = (new MpdfRenderer())->renderToString('tax-report', $document);
$response->getBody()->write($pdf);
return $response
    ->withHeader('Content-Type', 'application/pdf')
    ->withHeader('Content-Disposition', $disposition);
```

Letter is the default page. A report that needs another format or margins passes that to the renderer; keep the default in `MpdfRenderer`.

## Letterhead and language

`MpdfRenderer` fills `church_name`, address, phone, email, tax ID, website, social, and logo. The template prints them. `letterhead` is `address`, `graphic`, or `none`. `none` reserves blank space on every page so the letter still aligns.

A PDF that holds one letter per family or person (a giving statement run for many families) starts every letter with the letterhead: `{% include "_letterhead.html.twig" %}` right after each `<pagebreak />`, not only once at the top. A roster that continues across pages (class list, attendance sheet, photo book) needs no repeat.

Locale and RTL come from `MpdfRenderer` (`lang`, `text_dir`). mPDF script fonts cover CJK, Thai, Indic, Ethiopic, Hebrew, and Arabic names.

## Data rules

- User-visible strings go through `gettext()`.
- Money goes through `CurrencyFormatter`.
- Display dates use `sDateFormat*` / `sTimeZone`. Storage and API dates stay ISO `Y-m-d`.
- Year links use `FiscalYearUtils` and `iFYMonth`.
- Mailing address comes from `Family::getMailingAddressParts()` (includes Address2 and the secondary flag) when the letter is mailed.
- An empty result returns to the form with a message.

## People and photos

- A report that lists people shows a photo box for every person, so one layout covers people with and without photos. No "with pictures" option, no per-class variant.
- Take the photo from `$person->getPhoto()->getPhotoURI()` and use it only when `is_file()` is true.
- With no photo, fill the same box with `$person->getInitial(SystemConfig::getIntValue('iPersonInitialStyle'))`. Do not build initials by hand.
- Set image and placeholder sizes inline (`style="width: 8mm; height: 8mm;"`). mPDF ignores a `<style>` rule that targets an `img` inside a cell, and the photo prints at full size.
- Keep photos small in list reports (about 8 mm). Photo books and directories set their own larger frame.

## Tests

One Cypress spec per report. Intercept the download route, assert status and `application/pdf` (or CSV). Cover the empty-result path. See `cypress-testing.md`.
