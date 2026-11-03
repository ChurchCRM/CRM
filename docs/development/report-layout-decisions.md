# Shared PDF layout decisions

Attendance uses the shared 8mm portrait/initials partial. Its optional portrait
column is 10mm wide and photo rows are 10mm high; compact sheets use 6mm rows.

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

Use `_letter-break` for section-level church headers. Rendering and review checks
live in [Reports](../../.agents/skills/churchcrm/reports.md). Attendance coverage:
`cypress/e2e/ui/groups/sundayschool-class-attendance.spec.js`.
