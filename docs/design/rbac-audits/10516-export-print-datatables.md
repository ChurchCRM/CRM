# RBAC audit #10516: bulk export, print and DataTables extraction

**Status:** audit and design input only, for maintainer review. Not for merge. No runtime change is proposed or authorized here.
**Issue:** [#10516](https://github.com/ChurchCRM/CRM/issues/10516) · **Epic:** [#10922](https://github.com/ChurchCRM/CRM/issues/10922) · **Depends on:** [#10515 report](10515-person-family-fields.md) · **Design:** [RFC 0002](../../decisions/0002-rbac-redesign.md)
**Code baseline:** `master` at [`914c437`](https://github.com/ChurchCRM/CRM/tree/914c437b8e87da5db47a2b36419b004407596214). Links are pinned to that commit.

## Bottom line

1. **The dedicated CSV export is admin gated.** `CSVExport.php` and `CSVCreateFile.php` call `isAdmin()` first ([`CSVExport.php:11-13`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/CSVExport.php#L11-L13), [`CSVCreateFile.php:19-21`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/CSVCreateFile.php#L19-L21)). Full database backup and import are admin too.
2. **Every other bulk path is open to every staff account.** The people list, family list, People Reports and their CSV, mailing labels (PDF and CSV), the directory PDF, class lists, the photo book and the map feed need no permission beyond being staff.
3. **The DataTables buttons are not the boundary, and removing them would not be one.** On the people list and the family list, the server writes **every matching row and every column into the HTML** before DataTables starts. The CSV and print buttons only reformat what the browser already holds. "All" in the page-length menu shows the same rows.
4. **API feeds send more than the table shows.** The group roster feed sends the full person row (envelope, birth year, membership date, work email) for any staff account, though the group page itself needs `ManageGroups`. The person JSON does the same. The email-log feed is the counter-example: it is server-paged and redacted per viewer.
5. **There is no export audit trail.** No output path records who extracted which dataset, with which filter, or how many rows. Only backup downloads write a log line.
6. **A private finding needs maintainer review.** It has been reported privately and is not described here.

## Method and limits

- Inventory built from `plugin.dataTable` callers, every `extend: "csv"|"print"` button, and every PHP file that emits CSV, PDF or `Content-Disposition`. Each row was then read by hand.
- Only CSV and print buttons exist. `buttons.html5` and `buttons.print` are imported; `jszip` and `pdfmake` are not, so Excel, PDF and copy buttons are unavailable ([`vendor-globals.js:20-22`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/webpack/vendor-globals.js#L20-L22)).
- Not run against a live stack. Row counts and payload sizes are from code, not from traces.
- Screenshots and copy/paste cannot be prevented; this audit does not claim otherwise.

## 1. Vocabulary used below

| Term | Meaning | Server or client |
|---|---|---|
| **View** | Read one record or a page of records on screen | Either |
| **Limited print** | Print or PDF of one record, one family, one class or one event that the user may already view | Server PDF or browser print |
| **Filtered export** | A file of rows chosen by a filter or cart that the user may view, with a bounded column set | Server-generated file |
| **Bulk export** | Whole-dataset file (all people, all families, all addresses) or a file with columns beyond the on-screen set | Server-generated file |
| **Client-side extraction** | DataTables CSV/print of rows already in the browser | Client only; protection must happen before the rows are sent |

## 2. DataTables configuration

`dataTableConfig()` in [`webpack/crm-bootstrap.js:21-50`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/webpack/crm-bootstrap.js#L21-L50) sets, for every table that merges `window.CRM.plugin.dataTable`:

- buttons: CSV and print, with `exportOptions.columns = ":not(.no-export)"`
- page lengths 10, 25, 50, 100 and **All** (`-1`)
- client-side processing (no `serverSide`). DataTables buttons export every row that matches the current search, not only the visible page.

`.no-export` hides a column from the button output only. The cell is still in the DOM and in the DataTables data. Only one table in the app uses `serverSide: true` ([`email-log-table.js:132`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/webpack/people/email-log-table.js#L132)).

## 3. DataTables inventory

"Feed" is where the rows come from. "All staff" includes zero-permission staff.

| Table | Page gate | Feed and its gate | Rows sent to the browser | Sensitive columns sent | Class today |
|---|---|---|---|---|---|
| People list `/people/list` ([`person-list.php:704-706`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/person-list.php#L704-L706)) | All staff | Server-rendered DOM ([`people/routes/people.php`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/routes/people.php) `listPeople`) | **Every person** matching active/inactive and URL filters | Email, phone, address, birth date, classification, family role, grade, properties, groups, deceased; custom columns (empty for non-admins, see #10515 V3) | Bulk via client |
| Family list `/people/family` ([`family-list.php:196-197`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/family-list.php#L196-L197)) | All staff | Server DOM ([`people/routes/family.php:79-107`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/routes/family.php#L79-L107)) | Every family | Address, phone, email, members | Bulk via client |
| Cart `/people/cart` | All staff | Server DOM of the session cart | Cart contents | Name, address, email | Filtered via client |
| Group list ([`GroupList.js:58-61`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/skin/js/GroupList.js#L58-L61)) | ManageGroups (groups app) | `GET /api/groups/` — all staff | Every group | Counts | Low |
| Group members ([`group-view.js:368-370`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/webpack/groups/group-view.js#L368-L370)) | ManageGroups | `GET /api/groups/{id}/members` — **all staff**, no group scope ([`people-groups.php:313-339`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/api/routes/people/people-groups.php#L313-L339)) | Whole roster | **Full nested person row**: envelope, birth year (even with hide age), membership date, emails, phones, flags | Overfetch |
| Sunday school class and dashboard | ManageGroups | Server DOM | Class roster | Children, birthdays, parents | Filtered via client |
| Event check-in ([`event-checkin.js:26`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/webpack/event-checkin.js#L26)) | ViewEvents | Server DOM | Event roster | Names | Low |
| Main dashboard latest/updated families ([`MainDashboard.js:210-246`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/skin/js/MainDashboard.js#L210-L246)) | All staff | `/api/families/latest`, `/updated` (10 rows) | 10 | Address | Low |
| People without email ([`email/without.php:38-41`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/email/without.php#L38-L41)) | `bEmailMailto` (`/people/email` group) | `/api/persons/email/without` — all staff | All matching | Name, family | Filtered via client |
| Duplicate emails ([`email/duplicate.php:23-25`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/email/duplicate.php#L23-L25)) | `bEmailMailto` | `/api/persons/duplicate/emails` — EditRecords | All duplicates | Email | Page/feed mismatch: an email-only user gets the page and a 403 feed |
| Verify (complete / pending) ([`people-verify-view.php:409-486`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/people-verify-view.php#L409-L486)) | EditRecords | `/api/families/self-verify`, `/pending-self-verify` — EditRecords | All | Family contact | Filtered via client |
| Self-registrations ([`self-register.js:210-211`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/webpack/people/self-register.js#L210-L211)) | All staff | `/api/persons/self-register` — all staff | All pending | Contact, birthday | Filtered via client |
| Family pledges and payments ([`FamilyView.js:139-270`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/skin/js/FamilyView.js#L139-L270)) | Finance section | Finance API | One family | Amounts | Limited |
| Deposits, deposit payments, funds, contributors, pledge dashboard | Finance | Finance API or DOM | All deposits / all contributors | Amounts, donors | Bulk finance via client |
| Fundraisers | ManageFundraisers | DOM | All | Donors | Bulk via client |
| Users, logs, orphaned files, kiosk manager, custom links | Admin | Admin APIs | All | Usernames, flags | Admin |
| Ministries v2 dashboards ([`ministries/components/ui.ts:207-222`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/webpack/ministries/components/ui.ts#L207-L222)) | `VolunteerAuthorizationService` scopes | Scoped APIs | Scoped | Names, schedules | Scoped; buttons kept |
| MailChimp missing / not subscribed ([`list-missing.php:79-81`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/plugins/core/mailchimp/views/list-missing.php#L79-L81)) | Auth only (plugin) | Plugin API — auth only ([`PluginManager.php`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Plugin/PluginManager.php) adds no role middleware) | All matching | **Email addresses** without `bEmailMailto` | Bulk email list |
| Email log ([`email-log-table.js:132`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/webpack/people/email-log-table.js#L132)) | Admin / email dashboard | `/api/email/log`, server-paged, `forViewer()` redacts for non-admins ([`email-log.php:72-92`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/api/routes/email-log.php#L72-L92)) | One page | Redacted | **Model to copy** |

## 4. Server-generated outputs

| Output | Route | Format | Gate | Dataset and scope | Class |
|---|---|---|---|---|---|
| Dedicated CSV export | `CSVExport.php` → `CSVCreateFile.php` | CSV | **Admin** | Chosen person/family columns incl. custom fields | Bulk (admin) |
| CSV import | `/admin/api/import` | — | Admin | Writes | — |
| Database backup and restore | `/admin/api/database/*`, external-backup plugin | SQL / archive | Admin | Everything | Bulk (admin) |
| People Reports | `/people/reports/{slug}` and `/csv` ([`people/routes/reports.php:17-22`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/routes/reports.php#L17-L22)) | HTML, CSV | `StaffRoleAuthMiddleware` (all staff) | Birthdays, membership anniversaries, wedding anniversaries, people by grade, property with values, volunteers, missing people, recent friends | Filtered export, whole church per filter |
| Mailing labels | `Reports/PDFLabel.php` ([`:833-860`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/Reports/PDFLabel.php#L833-L860)) | PDF, **CSV** | All staff | Cart or People Report rows: greeting, name, mailing address | Filtered export; CSV is a name+address file |
| Newsletter labels, confirm labels | `Reports/NewsLetterLabels.php`, `ConfirmLabels.php` via `LettersAndLabels.php` | PDF | All staff | **Every family** (newsletter: opted in) with mailing address | Bulk print |
| Name tags | `Reports/NameTags.php` | PDF | All staff | Cart | Limited print |
| Directory | `DirectoryReports.php` → `Reports/DirectoryReport.php` | PDF | All staff | Whole church, optional cart/group/classification filter; contact, birthday, wedding, photos as chosen | Bulk print |
| Photo book | `Reports/PhotoBook.php` | PDF | All staff (launched from the ManageGroups Sunday school reports page) | Class photos and names | Filtered print, minors |
| Class list / class attendance | `Reports/ClassList.php`, `ClassAttendance.php` | PDF | All staff (the class pages need ManageGroups) | Children, birth dates, photos, parents' home phone | Filtered print, minors |
| Group report | `Reports/GroupReport.php` | PDF | All staff | Group roster, contact fields, group-specific fields | Filtered print |
| Verify report | `/people/report/verify` (download), `/email` (send) | PDF, email | EditRecords | One or every family | Bulk print |
| Voting Members | `Reports/VotingMembers.php` | PDF | Not published here; see section 8 "Private" | — | — |
| Group phones | `GET /api/groups/{id}/phones` | JSON | All staff | Cell phones of a group | Recipient list |
| Email recipient lists | `/api/people/emails`, `/api/cart/emails`, `/api/groups/{id}/emails` | JSON | `bEmailMailto` | All people / cart / group | Recipient list |
| Sunday school exports | `/api/groups/sundayschool/export/email`, `/classlist` ([`people-groups.php:693-881`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/api/routes/people/people-groups.php#L693-L881)) | CSV | ManageGroups | Every class | Bulk, minors |
| Map feed | `GET /api/map/families` | JSON | All staff | Every family and person with coordinates and address | Bulk location |
| Finance outputs | `/api/deposits/{id}/pdf|csv`, `/api/deposits/csv`, `Reports/TaxReport.php`, `AdvancedDeposit.php`, `EnvelopeReport.php`, `FamilyPledgeSummary.php`, `ReminderReport.php`, `ZeroGivers.php`, `FamilyTaxReportEmail.php` | PDF, CSV, email | Finance | Giving | Bulk finance |
| Fundraiser reports | `/fundraiser/{id}/reports/*` | PDF | ManageFundraisers | Donors, items | Filtered |
| Calendar feeds | `/api/public/calendar/{token}/*`, portal calendar token | ICS, JSON | Bearer token | Events | Not person data |
| Browser print of any page | — | — | Same as the page | What is on screen | Cannot be gated |

## 5. Do restricted rows or fields reach the browser regardless of buttons?

| Data class | Reaches the browser of a user who should not export it? | Where |
|---|---|---|
| Whole congregation (names, contact, birth dates) | **Yes**, by design today (read default) | People list DOM, family list DOM, map feed |
| Finance identifiers (envelope) | **Yes** for non-finance staff | Group roster JSON, person JSON, profile pages |
| Birth year under hide age | **Yes** | Person and roster JSON |
| Custom fields restricted by `FieldSecurity` | No on the people list and person JSON (helpers fail closed) | #10515 V3 |
| Private notes | No (timeline omits; API 403) | — |
| Children | **Yes** for all staff | People by Grade report, class list PDF, people list "Grade" column |
| Restricted people (D1) | Feature does not exist | — |

Conclusion: removing or hiding the CSV and print buttons today would not reduce what a staff account can take. Protection has to be applied where rows and columns are chosen on the server.

## 6. Verified and suspected gaps

### Verified

| ID | Gap | Evidence |
|---|---|---|
| E1 | People and family lists send every matching row and column to the browser; buttons only reformat it | `person-list.php`, `family-list.php`, `crm-bootstrap.js:21-50` |
| E2 | Group roster feed: open to all staff while the group page needs ManageGroups, and sends the full person row | `people-groups.php:313-339`, `groups/index.php` |
| E3 | Class list, class attendance and photo book PDFs are open to all staff while the Sunday school pages that launch them need ManageGroups | `Reports/ClassList.php`, `ClassAttendance.php`, `PhotoBook.php`, `groups/index.php` |
| E4 | Mailing labels export a name+address CSV for any staff account | `PDFLabel.php:833-860` |
| E5 | Plugin routes get no role middleware; the MailChimp list views return email addresses without `bEmailMailto` | `plugins/index.php`, `PluginManager::registerPluginRoutes` |
| E6 | Duplicate-email page needs `bEmailMailto` but its feed needs EditRecords | `people/routes/email.php:14-20`, `people-persons.php:24` |
| E7 | No export audit events anywhere except a backup-download log line | `BackupDownloader.php:20` |
| E8 | Page length "All" is offered on every merged table | `crm-bootstrap.js:25-28` |
| E9 | Map feed returns every geocoded family and person to all staff | `api/routes/map.php` |

### Suspected

| ID | Suspicion | How to confirm |
|---|---|---|
| ES1 | `PDFLabel.php:479` calls `print_r()` without `true`, which writes the label array into the response when bulk presort is on | Generate presorted labels; inspect the response |
| ES2 | Large churches: the people list DOM may exceed browser limits before any button is used | Seed 10k people, time the page |

### Private

One finding is withheld from this public report and reported to the maintainer privately.

## 7. Proposed capability and dataset-scope matrix (for review)

Capability keys follow RFC 0002 §4. Each grant binds action, dataset and scope as one unit (D2, S3).

| Capability | Means | Proposed default holders |
|---|---|---|
| `people.read` | View records in scope | Read-only staff and above (P5) |
| `people.print.limited` | Print one record, family, class or event in scope | Read-only staff and above |
| `people.export.filtered` | File of rows chosen by filter or cart, bounded columns, in scope, row cap | Office staff, Membership officer |
| `people.export.bulk` | Whole-dataset file or extended columns | Explicit grant only; not implied by admin for confidential classes |
| `people.labels.print` | Mailing and newsletter labels (address data) | Office staff |
| `people.contactlist.export` | Email and phone recipient lists | Holders of today's `bEmailMailto` |
| `minors.export` | Any output that lists children with contact or birth data | Children's ministry leads, scoped to their classes |
| `finance.export` | Finance outputs | Finance manager |
| `custom.export.<class>` | Custom-field columns of that field class | Per field class (#10515 §9) |
| `pastoral.export` | Notes or pastoral fields | None by default |
| `system.backup` | Full database backup | Administrator (configuration, documented as full access) |

| Dataset | View | Limited print | Filtered export | Bulk export |
|---|---|---|---|---|
| Ordinary directory | Read-only staff | Read-only staff | `people.export.filtered` | `people.export.bulk` |
| Contact lists | Read-only staff | — | `people.contactlist.export` | `people.export.bulk` |
| Children | Read-only staff (until D1/D3 decide otherwise) | Scoped class leads | `minors.export` scoped | `minors.export` + `people.export.bulk` |
| Custom fields | Per field class | Per field class | `custom.export.<class>` | `custom.export.<class>` + bulk |
| Finance | Finance | Finance | `finance.export` | `finance.export` |
| Pastoral notes | Per note policy (D4) | Per note | `pastoral.export` | Never in a bulk file |
| Restricted people (D1) | Explicit grant | Explicit | Explicit | Never in a bulk file without its own grant |

Rules: server enforces each capability at the output endpoint and at every feed that powers a client-side table; hidden buttons are UX only. Feeds return only the columns the table shows and the viewer may read (allowlisted DTOs, as `forViewer()` does). Large lists move to server-side paging before any export restriction is advertised.

## 8. Negative tests to write before implementation

| Category | Scenario | Expected |
|---|---|---|
| Unprivileged CSV | Read-only staff requests `/people/reports/{slug}/csv` once `people.export.filtered` exists | 403 from the endpoint |
| Unprivileged print | Read-only staff requests `Reports/PDFLabel.php?filetype=CSV` | 403 (today 200) |
| Bulk print | Read-only staff requests newsletter/confirm labels and the directory PDF | 403 without `people.labels.print` / bulk |
| Direct URL | Every report in section 4 requested by URL without the menu | Same decision as the menu |
| API overfetch | Zero-permission staff calls `/api/groups/{id}/members` | No `Envelope`, `BirthYear` under hide age, `MembershipDate` |
| Page vs feed | User without ManageGroups calls the group roster and class list endpoints | Same answer as the page |
| Client feed | People list HTML for read-only staff | Contains only columns that user may read; row count bounded by server paging once enabled |
| Restricted people | Restricted person (D1) in any list, label, report, feed or count | Absent, no count leak |
| Confidential custom field | Field with a restricted class, every output in section 4 | Absent from header and value |
| Cross-ministry scope | Ministry coordinator exports another ministry's roster | 403 |
| Minors | Read-only staff requests class list PDF and People by Grade CSV | Denied without `minors.export` |
| Plugin | Read-only staff calls MailChimp list endpoints | Denied without `people.contactlist.export` |
| Audit | Every allowed bulk/filtered export | One audit event with actor, dataset, filter, row count, format; no row content |
| Portal | Portal user calls any staff export | 403 |

## 9. Migration, defaults and documentation

1. **No surprise loss.** Upgrade maps every current staff account to `people.read`, `people.print.limited`, `people.export.filtered` and `people.labels.print` (today's effective access), lists that mapping in the effective-access preview, and asks the administrator to narrow it. `bEmailMailto` maps to `people.contactlist.export`.
2. **New installs** (P5): read-only staff get `people.read` and `people.print.limited` only.
3. **Server first.** Trim feeds and add server paging to the people and family lists before any button change. Then gate endpoints. Hide buttons last, from the same decision.
4. **Audit events** for filtered and bulk exports: actor, dataset, filter summary, row count, format, time. Never row content.
5. **Docs:** a church-facing page that explains view vs print vs export, says screenshots and copying cannot be stopped, and lists which outputs each template can produce. Docs PR waits for the release (`pr-review.md`).
6. **Accessibility, mobile, localization:** the denial for a hidden or disabled export control needs a translated reason, keyboard focus and a phone layout; button groups already sit in `topEnd` and wrap on phones.
