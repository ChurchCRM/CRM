# Shared PDF layout decisions

The response/filename pass (#11044) and shared class/voting/photo-book portraits
(#11046) are merged. Attendance now uses the same validated photo preparation and
8mm portrait/initials partial. Its optional portrait column remains 10mm wide and
its photo rows remain 10mm high; compact attendance sheets retain 6mm rows.

| Layout | Shared contract or retained reason |
| --- | --- |
| Ordinary letters and tables | `_base`, `_letter` and `_letterhead` own the page frame, church identity, table and monetary-total styles. Keep these shared defaults. |
| Class and voting lists | One 8mm portrait/initials partial. Class contacts need fixed phone/birthday columns; voting lists need eligibility rules and a people count, rather than financial totals. |
| Attendance | Landscape calendar grid, repeated month/day headings, closed-day shading and per-date count cells. These are handwriting space, not monetary-total rows; preserve their geometry. |
| Photo book | Larger aspect-preserving portraits and a matching missing-photo frame support learning faces; its four-column layout and compact footer differ intentionally from contact lists. |
| Group directory | Contact entries use a two-column reading layout rather than the photo-book grid or the attendance calendar. |
| Giving/zero-giver letters and family pledge summary | Shared letter frame; amounts and the tax-ID opt-in belong to their financial purpose. Do not apply financial identifiers to ordinary rosters. |
| Fundraiser catalog | Repeated compact catalog header and item-code section breaks support browsing; its continuation-page top margin reserves that header's space. |
| Bid sheets and certificates | Bid sheets reserve handwriting rows; certificates use larger presentation headings. Their typography and page breaks serve different tasks. |
| Fundraiser statements | One buyer's purchases and remittance section per statement; keep these with the statement body. |
| Labels/name tags and bank tickets | Positioned output follows the selected stock. Keep label offsets, deposit perforation/stub coordinates and separate deposit-summary margins. Physical alignment is tracked in #11015. |

Section-level church headers are tracked separately in #11005. The Church
Directory migration is #10981. Neither is folded into portrait consolidation.

## Validation record

Rendered the repository's attendance, class-list, voting and photo-book Twig
files using mPDF 8.3.1 and Twig 3.30.0 in an isolated PHP 8.3 template harness.
English and Arabic samples include photos and initials. Attendance exercises both
portrait and compact modes, two classes and continuation pages; class/voting
lists span multiple pages. Inspected first and final pages for all ten PDFs.
The attendance grid, portrait bounds, initials, totals row and RTL direction
remain legible, and the other three layouts retain their intended differences.
This is template-level evidence with synthetic data, not an application or
permission test.

Route-level Cypress coverage lives in
`cypress/e2e/ui/groups/sundayschool-class-attendance.spec.js`: both configured
initials styles, uploaded portraits bounded to 8mm, compact output and existing
permission/date cases. Local execution requires the supported application stack;
CI execution is recorded in the PR. No physical-stock alignment is claimed.
