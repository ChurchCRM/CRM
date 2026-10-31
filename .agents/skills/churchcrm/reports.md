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

Choose a stable download basename, validated date/id and matching extension. Build Content-Disposition from server-controlled values; preserve an established API filename contract. Unicode text belongs in the PDF; a Unicode download filename needs standards-compliant encoding and an ASCII fallback.

## Letterhead and language

`MpdfRenderer` fills `church_name`, address, phone, email, tax ID, website, social, and logo. The template prints them. `letterhead` is `address`, `graphic`, or `none`. `none` reserves blank space on every page so the letter still aligns.

Locale and RTL come from `MpdfRenderer` (`lang`, `text_dir`). mPDF script fonts cover CJK, Thai, Indic, Ethiopic, Hebrew, and Arabic names.

## Output safety

Use MpdfRenderer for mPDF construction and trace wrappers to the Twig Environment that renders the document. Verify HTML autoescaping for the actual environment, including injected loaders and shared partials. Prove markup in a name, memo or address prints literally; use reviewed template markup and nl2br for formatting. Keep template selection server-controlled.

Write PDF and CSV bodies through the response and set private/no-store headers for financial and personal data. Check helpers that emit headers or exit: they can bypass response-level headers. Keep CSV formula protection. Preserve supported API entry points and their authorization when migrating a report.

## Data rules

- User-visible strings go through `gettext()`.
- Money goes through `CurrencyFormatter`.
- Keep Unicode end to end; let the shared renderer provide language, direction and script fonts. Exercise non-Latin and RTL content in the complete document, including repeated headers and remittance sections.
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

Test the persona's complete workflow through its owning module, including filters, PDF/CSV totals, empty/error preservation, supported API calls and denied access. See cypress-testing.md for the current test policy and PDF text helper.

Distinguish content/status checks, visual PDF inspection and physical stock alignment. A valid PDF header proves neither layout nor printer alignment. Inspect ordinary and RTL documents; print prepositioned tickets/labels on the intended stock at actual size before claiming alignment.
