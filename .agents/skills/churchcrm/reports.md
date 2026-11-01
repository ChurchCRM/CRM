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
return PdfResponse::create($response, $pdf, 'GivingStatements-' . $year);
```

Letter is the default page. Pass landscape or report-specific format/margins through the existing renderer options; read its current signature. Labels, stock tickets and certificates own their layout; keep ordinary letter defaults in MpdfRenderer. Resolve graphic letterhead through the shared renderer helper and carry the selected letterhead mode into the document data.

## Files, names and local paths

| Responsibility | Location / convention |
| --- | --- |
| Query, filters and document data | `src/ChurchCRM/Service/<Report>Service.php` |
| Authorized page/download handler | `src/<module>/routes/`, registered by that module's `index.php` |
| Report options page | `src/<module>/views/reports/` |
| PDF document | `src/templates/reports/<report-name>.html.twig` |
| Shared PDF partial | `src/templates/reports/_<purpose>.html.twig` |
| Regression | Existing report specs under `cypress/e2e/`; follow `cypress-testing.md` |
| Workflow capture | `playwright/workflows/`; follow `marketing-visuals-pipeline.md` |

Pass the fixed template stem to the renderer: `'tax-report'` selects `tax-report.html.twig`. Keep template stems and module URLs stable, untranslated and in kebab-case. Keep namespaces and PHP class names in their existing PascalCase convention. Translate displayed titles and labels independently.

Resolve local assets with `SystemURLs::getDocumentRoot()` or the existing photo/logo service; build browser URLs with `SystemURLs::getRootPath()`. Validate that local image files exist. Preserve subdirectory installs and keep temporary output out of tracked source.

Use `PdfResponse` for module, portal and API PDF downloads and `PdfFilename` for email attachments. Supply a report suffix containing its purpose and relevant year/date/record or family name. The helpers add the church name and extension, normalize unsafe characters, and provide Unicode filenames with an ASCII header fallback.

## Letterhead and language

`MpdfRenderer` supplies church identity to the common `_letterhead.html.twig` partial. Extend `_base` or `_letter`. A report that prints one letter per family or recipient starts every letter after the first with `{% include "_letter-break.html.twig" %}` (page break plus letterhead), so each recipient's page carries the church header. Finance documents opt into tax ID with `show_tax_id`; report-specific titles and financial details belong in their document body. `letterhead` is `address`, `graphic`, or `none`. `none` reserves blank space on every page so the letter still aligns.

Locale and RTL come from `MpdfRenderer` (`lang`, `text_dir`). mPDF script fonts cover CJK, Thai, Indic, Ethiopic, Hebrew, and Arabic names.

## Output safety

Use MpdfRenderer for mPDF construction and trace wrappers to the Twig Environment that renders the document. Verify HTML autoescaping for the actual environment, including injected loaders and shared partials. Prove markup in a name, memo or address prints literally; use reviewed template markup and nl2br for formatting. Keep template selection server-controlled.

Keep PDF rendering separate from transport: `renderToString()` supplies bytes to downloads and emails; `PdfResponse` writes download bodies and sets private/no-store, nosniff and output-preference headers. HEAD handlers pass empty bytes and skip rendering. CSV uses the existing formula-safe exporter and private/no-store responses. Authorization stays in the owning module.

## Data rules

Every report reads the church's settings, so a report prints correctly for any church, language and fiscal year.

- The service returns display-ready strings: dates, money, translated labels, and sentences with placeholders (`sprintf(gettext('Amount due for %s: %s'), $fund, $amount)`). The template lays them out with loops and conditions and carries only static `gettext()` headings. A date format, currency symbol, number format or English word in a template is a defect.
- User-visible strings go through `gettext()`. Database codes (`CASH`, `CREDITCARD`) print as their translated labels.
- Money goes through `CurrencyFormatter` (symbol, position and separators come from settings). Sum in integer cents and format once.
- Display dates go through `DateTimeUtils::formatDate()` (`sDateFormatLong`, month name through `gettext`). A narrow table column may use `DateTimeUtils::formatForDatePicker()`. A month and day only uses `IntlDateFormatter` with the current locale. The renderer supplies `generated_on`. Filenames use `sDateFilenameFormat`. Storage, API and CSV dates stay ISO `Y-m-d`.
- Fiscal years use `FiscalYearUtils` (`getFiscalYearDatesById`, `formatFiscalYearLabel`, `getCurrentFiscalYearId`) and `iFYMonth`; a report never builds a year label or range itself.
- Keep Unicode end to end; let the shared renderer provide language, direction and script fonts. Exercise non-Latin and RTL content in the complete document, including repeated headers and remittance sections.
- Mailing address comes from `Family::getMailingAddressParts()` (includes Address2 and the secondary flag) when the letter is mailed.
- An empty result returns to the form with a message.

## Layout reuse

Compare near-matching layouts before adding a template or partial, including small differences in margins, photo frames, headings and totals. Share the common version when differences are incidental. Retain a difference only for an identified workflow, readability, locale or measured print-stock need, and record that decision in the PR. Labels and preprinted deposit tickets keep their measured geometry.

Update all current consumers when consolidating a report helper, then delete superseded methods and wrappers. Unreleased report code uses the shared contract directly.

## People and photos

- A report that lists people shows a photo box for every person, so one layout covers people with and without photos. No "with pictures" option, no per-class variant.
- Take the photo from `$person->getPhoto()->getPhotoURI()` and use it only when `is_file()` is true.
- With no photo, fill the same box with `$person->getInitial(SystemConfig::getIntValue('iPersonInitialStyle'))`. Do not build initials by hand.
- Set image and placeholder sizes inline (`style="width: 8mm; height: 8mm;"`). mPDF ignores a `<style>` rule that targets an `img` inside a cell, and the photo prints at full size.
- Keep photos small in list reports (about 8 mm). Photo books and directories set their own larger frame.

## Tests

Test the persona's complete workflow through its owning module, including filters, PDF/CSV totals, empty/error preservation, supported API calls and denied access. A letter report is exercised with two or more recipients, and the spec checks that the church name appears once per letter. A new report is added to the settings spec (`report-locale-settings.spec.js`), which runs the PDFs with `d.m.Y` dates, a trailing `€` and comma decimals and checks the text. See cypress-testing.md for the current test policy and PDF text helper.

Distinguish content/status checks, visual PDF inspection and physical stock alignment. A valid PDF header proves neither layout nor printer alignment. Inspect ordinary and RTL documents; print prepositioned tickets/labels on the intended stock at actual size before claiming alignment.

## Review evidence

Keep the consumer inventory in the PR: affected entry points, permissions, naming/header behavior and layout decisions. Verify the safety and test requirements above against the actual renderer and shared partials, and state which checks remain unverified. State the locale check: the dates, money and labels the PDF printed under non-US settings, or that it was not run.
