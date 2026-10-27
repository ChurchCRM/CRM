# RBAC audit #10515: person and family fields, labels, custom fields

**Status:** audit only, for maintainer review. Not for merge. No runtime change is proposed or authorized here.
**Issue:** [#10515](https://github.com/ChurchCRM/CRM/issues/10515) · **Epic:** [#10922](https://github.com/ChurchCRM/CRM/issues/10922) · **Design:** [RFC 0002](../../decisions/0002-rbac-redesign.md), decisions in [#10920](https://github.com/ChurchCRM/CRM/pull/10920)
**Code baseline:** `master` at [`914c437`](https://github.com/ChurchCRM/CRM/tree/914c437b8e87da5db47a2b36419b004407596214). Every link below is pinned to that commit.

## Bottom line

1. **Field-level access is not a separate layer today.** Except for custom fields and three finance values, every `person_per` and `family_fam` column is readable by every staff account, including zero-permission staff. Writes are gated per page by the coarse `EditRecords` / `AddRecords` flags. That matches the approved read default (P1, P5). It also means no field can be called restricted yet.
2. **Custom-field `FieldSecurity` has two evaluators that disagree.** Legacy pages map the stored number to a name and work. The ORM helpers pass the raw number, so a non-admin never matches any field, not even `bAll`. That fails closed, but it hides ordinary fields from the person page, the family page, the person list and `/api/person/{id}`, while the editor shows them.
3. **The finance values follow no single rule.** Family envelope and the scanned-check fields are stripped from family JSON for non-finance users. The family envelope is still shown on the family page, and any `EditRecords` user can overwrite it. Person envelope is shown to all staff and sent in person JSON, while only finance users can write it.
4. **Presentation flags are not access controls.** "Hide age" removes the birth year from formatted dates, but the year is still in the person JSON and the group roster JSON. `prop_PersonDisplay` on group-specific fields is a display toggle with no access check. Neither one protects data.
5. **Labels carry ordinary directory data only.** Mailing labels, newsletter labels, confirm labels and name tags print names and mailing addresses. They print no custom fields, notes or finance values, and they skip deceased people. Every staff account can produce them for the whole congregation, as PDF or CSV. That is a bulk-extraction question for #10516, not a field leak.
6. **Membership Date has no field-level gate** (verified, #10512). Any `EditRecords` user can change it, and any `AddRecords` user can set it when creating a person. The `EditSelf` branch in `PersonEditor.php` is unreachable in practice: `PageInit.php` sends every non-admin `EditSelf` user to the portal first.
7. **A private finding needs maintainer review.** It is reported separately and is not described here.

## Method and limits

- Every claim was checked in source at the baseline commit. Claims marked **suspected** were not run against a live stack.
- No Cypress run, live database or browser session was used for this audit.
- Volunteer v2 scopes (`VolunteerAuthorizationService`), finance pages and the kiosk audit (#10518) are referenced but not re-audited here.
- F-03 (account email and recovery) is closed under RFC 0002 §5 and is not reopened. Email fields are listed for completeness only.

## 1. Principals as they exist today

| Principal | How it is recognized | What it reaches | Source |
|---|---|---|---|
| Unauthenticated | No session | `/api/public/*`: self-registration writes a limited person/family with `NeedsReview` | [`api/routes/public/public-register.php:112-287`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/api/routes/public/public-register.php#L112-L287) |
| Kiosk device | Kiosk cookie, accepted device | Assigned class roster, allowlisted fields, guest registration | [`kiosk/routes/device.php:310-680`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/kiosk/routes/device.php#L310-L680) |
| Portal-only (`EditSelf`, not admin) | `isEditSelfExclusive()` | `/portal`, `/api/portal`, Volunteer v2 member APIs. Blocked from all staff pages and APIs | [`User.php:151`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L151), [`AuthMiddleware.php:69,87`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Slim/Middleware/AuthMiddleware.php#L69), [`PageInit.php:26`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/Include/PageInit.php#L26) |
| Zero-permission staff | All flags 0 | Reads every person and family, every report and label page | [`User.php:451-470`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L451-L470) |
| Staff with module flags | `AddRecords`, `EditRecords`, `DeleteRecords`, `ManageGroups`, `Finance`, `Notes`, ministry flags, `userconfig_ucfg` (`bEmailMailto`, `bAddEvent`) | Read as above, plus the writes for each flag | [`User.php:156-235`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L156-L235) |
| Administrator | `usr_Admin` | Every getter returns true. Reads others' private notes. Sees every custom field. Defines custom fields. CSV export and import | [`User.php:557`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L557), [`User.php:795-798`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L795-L798) |
| API key | `x-api-key` header | Same as the owning user. No scopes | [`AuthMiddleware.php:28-74`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Slim/Middleware/AuthMiddleware.php#L28-L74) |
| In-process plugin | PHP code in `src/plugins` | Full ORM access. Its routes carry no role middleware unless the plugin adds one | [`plugins/core/mailchimp/routes/routes.php`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/plugins/core/mailchimp/routes/routes.php) |

Guardian, board member, membership officer, pastor and trusted group **do not exist as principals today**. A family role (`per_fmr_ID`) is a household label. A group role (`p2g2r_rle_ID`) is a roster label. Neither one grants anything in code.

## 2. Sensitivity tiers used in this audit

| Tier | Meaning | Examples |
|---|---|---|
| T0 System | Keys and audit stamps | `per_ID`, `per_DateEntered`, `per_EditedBy` |
| T1 Directory | Who someone is in the church | Names, family name, family role, gender, photo, group membership |
| T2 Contact | How to reach someone | Addresses, phones, emails, social handles, geolocation |
| T3 Personal | Life details | Birth date and year, wedding date, deceased date, grade, friend date, "why came" |
| T4 Governance | Membership and lifecycle status | Classification, membership date, deactivation, review status, future voting eligibility |
| T5 Financial identifier | Ties a person to money | Envelope numbers, scanned check and card data |
| T6 Confidential | Pastoral or restricted content | Private notes, custom fields set to Notes/Admin, future highly confidential notes |
| Minor overlay | Raises any tier for a child | A person with a grade, or under the church's age of majority |

A custom field's tier is whatever the church configured. `FieldSecurity` stores a permission, not a tier (section 6).

## 3. Field inventory

"All staff" means every non-portal account, including zero-permission staff. "Portal" means the portal-only principal acting on itself or its family.

### 3.1 `person_per` ([`orm/schema.xml:18-98`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/orm/schema.xml#L18-L98))

| Column | Tier | Read today | Write paths and gate today | Gap or note |
|---|---|---|---|---|
| `per_ID` | T0 | All staff; numeric ID in every URL | — | IDs are not secrets. Record policy must not rely on them |
| `per_Title`, `per_FirstName`, `per_MiddleName`, `per_LastName`, `per_Suffix` | T1 | All staff: profile, list, search, API, labels, directory, name tags, photo book, rosters. Kiosk: first and last name for the assigned class. Portal: own and family | `PersonEditor.php` (Edit/AddRecords); `FamilyEditor.php` member rows (EditRecords); portal profile (self); public register; kiosk guest; CSV import (Admin) | None for ordinary records |
| `per_Address1`–`per_Country` | T2 | All staff; labels and directory use the family address first | PersonEditor; public register; import | — |
| `per_HomePhone`, `per_WorkPhone`, `per_CellPhone` | T2 | All staff; `/api/groups/{id}/phones` for any staff; portal sees family cell phones | PersonEditor; FamilyEditor; portal (self); public register; import | Group phone list carries no email-style permission (#10516) |
| `per_Email` | T2, identity-adjacent | All staff. Email lists need `bEmailMailto` ([`people-persons.php:586-593`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/api/routes/people/people-persons.php#L586-L593)) | PersonEditor, read-only for account holders unless admin ([`PersonEditor.php:168,222`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/PersonEditor.php#L168)); portal, locked when the actor has a login ([`PortalSelfService.php:234-237`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Portal/PortalSelfService.php#L234-L237)); kiosk guest; public register; import | F-03 closed. Old-address notice is #10917 |
| `per_WorkEmail` | T2 | All staff | PersonEditor; portal (not locked) | Not a sign-in identity today |
| `per_BirthMonth`, `per_BirthDay`, `per_BirthYear` | T3 | UI hides the year when "hide age" is set ([`Person.php:79-82`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/Person.php#L79-L82)). `Person::toArray()` sends all three to every staff account ([`Person.php:969-976`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/Person.php#L969-L976)) | PersonEditor; FamilyEditor; portal when `bPortalAllowBirthdayEdit`; public register; kiosk guest; import | **Verified:** hide age is presentation only |
| `per_MembershipDate` | T4 | All staff: profile ([`person-view.php:102`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/person-view.php#L102)), People Report "Membership Anniversaries", person JSON | PersonEditor with no field check ([`PersonEditor.php:229,438-441`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/PersonEditor.php#L438-L441)); import (Admin) | **Verified** #10512 |
| `per_DateDeceased` | T3/T4 | All staff | PersonEditor (EditRecords) | Labels skip deceased people ([`PDFLabel.php:639-642`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/Reports/PDFLabel.php#L639-L642)) |
| `per_Gender` | T1 | All staff; kiosk roster | PersonEditor; portal add member; public register; kiosk guest | — |
| `per_fmr_ID` (family role) | T1 | All staff; portal | PersonEditor; FamilyEditor; `POST /api/person/{id}/role/{roleId}` (EditRecords); cart to family (AddRecords); portal add member (adult); public register; import | Household label, **not guardianship**. Portal "adult" is derived from it ([`PortalSelfService.php:207`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Portal/PortalSelfService.php#L207)) |
| `per_cls_ID` (classification) | T4 | All staff; person list filter; reports | PersonEditor; FamilyEditor; kiosk guest registration, using the administrator's `iKioskGuestClassification` setting, never the request ([`device.php:647`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/kiosk/routes/device.php#L647)); import | The device cannot choose the value; an administrator sets it |
| `per_fam_ID` | T1, scope key | All staff | PersonEditor; FamilyEditor; cart to family; family delete unlinks members; import | It sets the `EditSelf` and portal family scope ([`User.php:480-528`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L480-L528)) |
| `per_Envelope` | T5 | All staff on the profile ([`people/routes/view.php:319`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/routes/view.php#L319)); person JSON and group roster JSON | PersonEditor only when finance ([`PersonEditor.php:456-458`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/PersonEditor.php#L456-L458)); import (Admin) | **Verified:** read is broad, write is finance |
| `per_DateLastEdited`, `per_DateEntered`, `per_EnteredBy`, `per_EditedBy` | T0 | All staff | Set by writers | The only person-level audit trail besides timeline notes |
| `per_FriendDate` | T3 | All staff; People Report "Recent Friends" | PersonEditor | — |
| `per_Flags` (hide age) | Flag | All staff | PersonEditor; public register | Presentation flag |
| `per_Facebook`, `per_Twitter`, `per_LinkedIn` | T2 | All staff | PersonEditor | — |
| `per_DateDeactivated` | T4 | All staff; person list shows active by default | `POST /api/person/{id}/activate/{status}` (EditRecords) | Lifecycle, not visibility (RFC 0002 §2) |
| `per_NeedsReview` | T4 | All staff | Approve: EditRecords. Set true: portal add member, public register, kiosk guest | — |
| `per_grd_ID` (grade) | T3, minor indicator | All staff; People Report "People by Grade"; class lists | PersonEditor; FamilyEditor; import | The only structured "this is a child" signal besides birth year |

### 3.2 `family_fam` ([`orm/schema.xml:153-201`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/orm/schema.xml#L153-L201))

| Column | Tier | Read today | Write paths and gate today | Gap or note |
|---|---|---|---|---|
| `fam_ID`, `fam_Name` | T0/T1 | All staff; portal own family | FamilyEditor (Edit/AddRecords); public register; import | — |
| `fam_Address1`–`fam_Country`, `fam_Second*`, `fam_SecondIsMailing` | T2 | All staff; labels; directory; map | FamilyEditor; portal adults (primary address); public register; import | — |
| `fam_Latitude`, `fam_Longitude` | T2 (precise location) | `/api/map/families` for all staff; `/api/family/{id}/geolocation` behind `FamilyMiddleware` | Geocoder (EditRecords) | — |
| `fam_HomePhone` | T2 | All staff | FamilyEditor; portal adults | — |
| `fam_Email` | T2 | All staff | FamilyEditor (no account lock, by design: not an identity); portal adults | Removal tracked in #10918 / #10919 |
| `fam_WeddingDate` | T3 | All staff; People Report anniversaries | FamilyEditor; portal adults | `bHideWeddingDate` hides the input only |
| `fam_scanCheck`, `fam_scanCredit` | T5 (bank routing+account, card scan) | Stripped from `Family::toArray()` for non-finance ([`Family.php:792-794`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/Family.php#L792-L794)). The person page selects `family_fam.*` into memory ([`view.php:110`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/routes/view.php#L110)) but never renders it | No writer in `src/` today. Lookup by check: `GET /api/families/byCheckNumber/{scan}` (Finance) | Legacy data may still exist |
| `fam_SendNewsLetter` | Preference | All staff; newsletter labels | FamilyEditor | — |
| `fam_DateDeactivated`, `fam_NeedsReview` | T4 | All staff | `POST /api/family/{id}/activate/{status}`, approve review (EditRecords) | — |
| `fam_Envelope` | T5 | **Shown to all staff on the family page** ([`family-view.php:514`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/family-view.php#L514)); stripped from family JSON for non-finance | **FamilyEditor, any EditRecords** ([`FamilyEditor.php:155-163,297`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/FamilyEditor.php#L155-L163)); `ManageEnvelopes.php` (Finance); import | **Verified:** three rules for one value |

### 3.3 Related stores

| Store | Tier | Read today | Write today | Note |
|---|---|---|---|---|
| `person_custom`, `family_custom` (dynamic `c1..cN`) and their `*_custom_master` definitions ([`schema.xml:131-152,202-224`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/orm/schema.xml#L131-L152)) | Per field | See section 6 | Values: PersonEditor / FamilyEditor (EditRecords plus per-field check), import (Admin). Definitions: admin only ([`PersonCustomFieldsEditor.php:17`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/PersonCustomFieldsEditor.php#L17)) | Definition and value permissions are already separate |
| `note_nte` ([`schema.xml:225-258`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/orm/schema.xml#L225-L258)) | T6 if private | Type `note` needs Notes. Private: author or admin ([`Note.php:80-96`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/Note.php#L80-L96)). Other note types (edit, group, verify) show to all staff on the timeline ([`TimelineService.php:103-116`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Service/TimelineService.php#L103-L116)) | Notes flag; `NoteEditor.php` | `Note::isVisible(int)` (author only, no admin) still exists beside `isVisibleTo()` |
| `record2property_r2p` + `property_pro` ([`schema.xml:839-874`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/orm/schema.xml#L839-L874)) | T1–T4, free-text value | All staff: profile, family page, person list column, People Report "Person by Property" with values. `GET /api/people/properties/person/{id}` needs EditRecords | EditRecords (people/families), ManageGroups (groups). Catalogue: admin | **Verified** page/API mismatch: the API is stricter than the page that shows the same data |
| `person2group2role_p2g2r` ([`schema.xml:325-348`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/orm/schema.xml#L325-L348)) | T1 (T6 if the group itself is sensitive) | All staff; `GET /api/groups/{id}/members` sends the full nested `Person` row ([`people-groups.php:313-339`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/api/routes/people/people-groups.php#L313-L339)) | ManageGroups. `/api/cart/emptyToGroup` has no route middleware, but `GroupService::addUserToGroup()` requires `bManageGroups` ([`GroupService.php:87`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Service/GroupService.php#L87)) | The service-only check answers 400, not 403 |
| `groupprop_<groupId>` (group-specific person fields) | Per field, no ACL | All staff on the profile when `prop_PersonDisplay` is set ([`person-view.php:785-813`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/person-view.php#L785-L813)); `Reports/GroupReport.php` (all staff) | ManageGroups (groups app `roleMiddleware`) | **Verified:** a third field store with no field security at all |
| `whycame_why` | T3 free text | `WhyCameEditor.php` only | EditRecords | — |
| Person and family photos | T1 (minor overlay) | `GET /api/person/{id}/photo` for any authenticated principal, `Cache-Control: public` ([`people-person.php:108-117`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/api/routes/people/people-person.php#L108-L117)); direct file access denied ([`Images/Person/.htaccess`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/Images/Person/.htaccess)) | EditRecords upload, DeleteRecords delete | **Suspected:** `public` lets a shared proxy cache a child's photo |
| `email_log_eml` | T2 (subject may imply T5) | Person timeline for all staff ([`TimelineService.php:166-199`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Service/TimelineService.php#L166-L199)) | Email services | **Suspected:** a giving-statement subject tells non-finance staff a family gives |
| `list_lst` (classifications 1, family roles 2, group types 3, field security 5) | Configuration | All staff | Admin | Catalogue, not record data |
| `user_usr` link | Identity | Admin sees account buttons on the profile | Admin user editor | Out of scope (F-03) |

## 4. Entrypoint matrix (read, write, search, export)

| Entrypoint category | Example routes | Record check | Field check | Verdict |
|---|---|---|---|---|
| Profile pages | `/people/view/{id}`, `/people/family/{id}` | `canReadPerson()` always true; `canViewFamily()` limits portal users only | Custom fields via ORM helper (hides all from non-admin, section 6); envelopes shown | Broad by design |
| Person JSON | `GET /api/person/{id}` | `canViewFamily()` | `exportTo('JSON')` → full `Person::toArray()`; custom fields replaced by the helper | All base columns, including envelope and birth year |
| Family JSON | `GET /api/family/{id}` | `FamilyMiddleware` | `Family::toArray()` strips envelope and scan data for non-finance | Only finance-aware serializer |
| Group roster JSON | `GET /api/groups/{id}/members` | None beyond auth | Full nested `Person` row | Overfetch (#10516) |
| Lists | `/people/list` | None | Custom columns via helper; all base columns in the DOM | Whole congregation in the browser (#10516) |
| Search and autocomplete | `GET /api/search/{q}`, `/api/persons/search/{q}`, `/api/families/search/{q}` | None | Names, emails, phones searched | Broad by design |
| Timeline | `/api/timeline/person/{id}` | `canReadPerson()` | Note type and private flag | Non-`note` types visible to all staff |
| Editors | `PersonEditor.php`, `FamilyEditor.php` | Edit/AddRecords | Per-field check on custom fields; finance check on person envelope only | Membership date, classification and family envelope unguarded by field |
| Portal | `/api/portal/*` | Actor and actor's family | Allowlists ([`PortalSelfService.php:45-73`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Portal/PortalSelfService.php#L45-L73)) | Narrowest surface today |
| Kiosk | `/kiosk/device/*` | Accepted device, assigned event | Allowlisted DTOs | Guest registration applies the configured classification |
| Public | `/api/public/register/*` | None | Allowlisted setters | Creates records that need review |
| Labels and letters | `LettersAndLabels.php`, `Reports/PDFLabel.php`, `ConfirmLabels.php`, `NewsLetterLabels.php`, `NameTags.php` | Staff (`PageInit.php`) | Name and mailing address only | Section 5 |
| People Reports | `/people/reports/{slug}` and `/csv` ([`people/routes/reports.php:17-22`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/routes/reports.php#L17-L22)) | `StaffRoleAuthMiddleware` | Fixed column sets; property values; membership dates | Bulk by design (#10516) |
| Legacy reports and global search | `DirectoryReports.php`, `/people/report/verify`, `Reports/*.php`, `/api/search` | Staff, EditRecords or Finance per page | Varies by report | Field-level verdicts for this category are not published here; see "Private" in section 10 |
| Dedicated CSV export and import | `CSVExport.php`, `CSVCreateFile.php`, `/admin/api/import` | Admin | Admin sees all | Admin gated, as stated in #10516 |
| Plugins | MailChimp lists and person lookup | Auth only | Emails | Email list without `bEmailMailto` (#10516) |

## 5. Labels, traced end to end

"Labels" covers two different things. Mailing and print labels are outputs. Classifications, properties, group roles and status badges are record attributes. Neither is a security tag today.

| Step | Mailing labels (`PDFLabel.php`) | Newsletter / confirm labels | Name tags |
|---|---|---|---|
| Selection | Session cart, or a People Report slug with its parameters ([`PDFLabel.php:833-839`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/Reports/PDFLabel.php#L833-L839)) | Every family, or every family with `fam_SendNewsLetter = TRUE` ([`NewsLetterLabels.php`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/Reports/NewsLetterLabels.php)) | Session cart ([`NameTags.php:27`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/Reports/NameTags.php#L27)) |
| Who may run it | Any staff (`PageInit.php` only) | Any staff | Any staff |
| Data on the output | Greeting, names, mailing address; "to parents" readdresses children to the household | Family name or salutation, mailing address | Person names |
| Filters | Deceased skipped; inactive people not skipped | No inactive filter | None |
| Formats | PDF or **CSV** | PDF | PDF |
| Can it disclose restricted data? | Not custom fields, notes or finance. It can disclose a child's name with the home address, and the whole congregation's addresses in one file | Whole-church address list | Names only |

Classifications are visible on profiles, the person list and its filter. Properties are visible as above. Group roles are visible on rosters. Status badges (inactive, deceased, needs review) are visible to all staff. None of them changes who can read a record today.

## 6. Custom-field `FieldSecurity`, traced

Storage: `custom_FieldSec` / `fam_custom_FieldSec` hold an option ID from `list_lst` list 5 ([`Install.sql:410-417`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/mysql/install/Install.sql#L410-L417)): 1 `bAll`, 2 `bAdmin`, 3 `bAddRecords`, 4 `bEditRecords`, 5 `bDeleteRecords`, 7 `bManageGroups`, 8 `bFinance`, 9 `bNotes`. Option 6 (`bMenuOptions`) was deleted in an old upgrade; a field still pointing at 6 resolves to nothing.

Evaluator: `User::isEnabledSecurity(string $name)` ([`User.php:795-838`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L795-L838)). Admin is always true. `bAdmin` is false for others. `bAll` is true. Each flag name maps to its getter. Any other name is looked up in `userconfig_ucfg`; an unknown name is false. One value controls both read and write.

| Caller | Passes | Result for a non-admin | Source |
|---|---|---|---|
| `PersonEditor.php` render and save | Mapped name | Correct | [`PersonEditor.php:70-78,348,493,1136`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/PersonEditor.php#L70-L78) |
| `FamilyEditor.php` save | Mapped name | Correct | [`FamilyEditor.php:419`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/FamilyEditor.php#L419) |
| `DirectoryReports.php` form | Mapped name | Correct on the form | [`DirectoryReports.php:178`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/DirectoryReports.php#L178) |
| `CSVExport.php` | Mapped name | Admin-only page, so moot | [`CSVExport.php:188,202`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/CSVExport.php#L188) |
| `Person::getVisibleCustomFieldDefinitions()` / `getVisibleCustomFieldValues()` | **Raw number** | Never matches: every field hidden, `bAll` included | [`Person.php:1089-1098`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/Person.php#L1089-L1098) |
| `Person::getCustomFieldsAll()` (person list) | Raw number | Same | [`Person.php:821`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/Person.php#L821), [`person-list.php:83,97`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/views/person-list.php#L83) |
| Family profile | Raw number | Same | [`people/routes/family.php:182`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/people/routes/family.php#L182) |

`php -r 'var_dump(1 == "bAll");'` prints `bool(false)` on PHP 8, which confirms the mismatch. The custom-field skill card (`custom-field-acl.md`) shows the helper as the correct pattern, so new code copies the failing path.

Consequences:
- Ordinary staff do not see `bAll` custom fields on the profile, family page, list or API. They do see them in the editor. Data is not exposed; the product looks broken and staff fall back to reports.
- A one-line fix (map the number to a name) changes which fields leave the database on four surfaces at once. Ship it with the negative tests in section 10, not on its own.
- Definition metadata: on the profile, list, editor and directory form, field names reach a non-admin only for fields that user may read.

## 7. Identity and recovery impact of field edits

| Edit | Who can make it today | Identity or recovery effect today | Note |
|---|---|---|---|
| Personal email (`per_Email`) | EditRecords; admin for account holders; portal self unless the person has a login | Used by sign-in and reset flows for account holders | F-03 closed with #10917 and #10649 |
| Family email | EditRecords; portal adults | None (not an identity) | Being removed (#10918) |
| Family role | EditRecords; portal adults when adding a member | Decides portal "adult" and so who may edit the family in the portal | Not proof of guardianship (P3) |
| Family link (`per_fam_ID`) | EditRecords, AddRecords via cart | Decides whose records a portal user can see and edit | Moving a person changes portal scope at once |
| Group membership | ManageGroups | None today. Would become a privilege path under P9 | Must be protected before any group grants rights |
| Envelope | Person: Finance. Family: EditRecords and Finance | Reassigns giving attribution for scanned or envelope entry | Finance integrity |
| Membership date, classification | EditRecords; kiosk applies the configured guest classification | Feeds anniversary reports and the Voting Members heuristic | Not voting eligibility (P10) |
| Inactive / deceased | EditRecords | Hides people from default lists and labels | Lifecycle, not visibility |
| Notes | Notes flag; private by author | — | D4 |
| Protected-person marker | Does not exist | — | D1 |

## 8. Proposed role × action × scope × field-class matrix (to-be, for decision)

This is a proposal, not current behavior. "Ordinary" means a standard record. Restricted records (D1) deny unless a grant names them. Deny wins (RFC 0002 D2). R = read, W = write, — = none.

| Role (template candidate) | Record scope | T1 Directory | T2 Contact | T3 Personal | T4 Governance | T5 Financial id | T6 Confidential | Custom fields |
|---|---|---|---|---|---|---|---|---|
| Unauthenticated | — | W (self-register, review queue) | W (same) | W birthday (same) | — | — | — | — |
| Kiosk device | Assigned event roster | R first/last name, photo | R guardian contact for pickup (per #10518) | — | — (guest classification stays an admin setting) | — | — | — |
| Portal member | Self | R/W allowlist | R/W allowlist | R; W birthday if enabled | — | — | — | Per-field portal flag (new) |
| Portal adult | Own household | R names; W add member | R/W household contact | R | — | — | — | — |
| Verified guardian (D3) | Linked children | R/W approved child fields | R/W | R/W approved | — | — | — | Approved list |
| Volunteer / check-in | Assigned roster | R | R pickup contacts only | — | — | — | — | — |
| Group / team leader | Assigned groups | R | R | R birthday without year | — | — | — | Group-scoped fields |
| Read-only staff (P5 default) | Ordinary | R | R | R | R | — | — | `bAll`-equivalent |
| Office staff / People editor | Ordinary | R/W | R/W | R/W | R (no membership date write) | — | — | Read/write per field |
| Membership officer | Ordinary | R | R | R | R/W classification, membership date; voting eligibility only with its own grant (P10) | — | — | — |
| Board member | — (no staff role by default) | — | — | — | — | — | — | — |
| Pastor / trusted pastoral group (P9) | Ordinary plus restricted by grant | R | R | R | R; W restricted marker (P7) | — | Shared notes only (P2) | Pastoral fields |
| Finance assistant / manager | Ordinary | R | R | — | — | R/W envelopes, scan data | — | Finance fields |
| Church administrator | Configuration | R | R | R | R | — unless also Finance | — (P6) | Defines fields; values per field |
| Service / API token | Token scope | Scoped | Scoped | Scoped | — by default | — by default | — | Scoped |

Combination rule for this matrix: a field is readable only when one complete grant covers the action, the record and the field class. A record-level allow never overrides a field-level deny. Delegation to a non-admin trusted group adds only the allowlisted actions in P9.

## 9. Legacy `FieldSecurity` → RBAC crosswalk

| Stored option | Meaning today | Proposed field class | Proposed permission | Migration rule |
|---|---|---|---|---|
| 1 `bAll` | Every staff account | `ordinary` | `people.custom.read` / `.update` with the record grant | Map 1:1. Fix the evaluator first so parity tests describe intended behavior |
| 2 `bAdmin` | Admin only | `restricted.admin` | `people.custom.restricted.read` | Do not map to the administrator template. List each field in the upgrade report and ask the church whether it is configuration or confidential (P6) |
| 3 `bAddRecords`, 4 `bEditRecords`, 5 `bDeleteRecords` | Users with that flag | `ordinary.editor` | Read with `people.update` | Map to People editor. Report fields where "readers" were narrower than "editors" |
| 7 `bManageGroups` | Group managers | `group` | Group-scoped read | Map to group manager, scoped to all groups (today's global flag) |
| 8 `bFinance` | Finance users | `finance` | `finance.person.read` | Map to Finance templates |
| 9 `bNotes` | Notes users | `pastoral` | `notes.read`-equivalent field grant | Map to Pastoral template; never to admin |
| 6 or any missing option | Resolves to nothing | — | **Deny** | Fail closed, list in the upgrade report |
| Custom option names (admin-added to list 5) | Looked up in `userconfig_ucfg` | Unknown | **Deny** | Fail closed; offer an explicit mapping |
| Admin bypass | Admin reads every field | — | No implicit bypass for `pastoral` or `restricted.admin` | Needs a D2/P6 decision before enforcement |

Also: split read from write per field (one value does both today), and keep definition management (`people.custom.define`, admin) separate from values.

## 10. Verified and suspected gaps

### Verified in code

| ID | Gap | Evidence |
|---|---|---|
| V1 | Every base person/family column is readable by every staff account; record checks always allow | `User.php:451-470` |
| V2 | Membership date has no field-level write gate | `PersonEditor.php:229,438-441` |
| V3 | ORM custom-field helpers pass the number, not the name; non-admins see no custom fields on four surfaces | Section 6 |
| V4 | Envelope numbers: four different rules across person/family read and write | `view.php:319`, `family-view.php:514`, `Family.php:792-794`, `PersonEditor.php:456`, `FamilyEditor.php:155-163` |
| V5 | Hide age is presentation only; birth year is in person and roster JSON | `Person.php:79,969` |
| V6 | Group-specific person fields have no access control; the display flag is not security | `person-view.php:785-813` |
| V7 | Property API is stricter than the pages that show the same data | `people-properties.php:24-25` vs profile and People Report |
| V8 | The `EditSelf` branches in `PersonEditor.php` / `FamilyEditor.php` are unreachable for non-admins | `PageInit.php:26`, `User.php:151` |
| V9 | Kiosk guest registration writes classification from an admin setting (`iKioskGuestClassification`), not from the request. Recorded so RBAC keeps it that way | `device.php:647` |
| V10 | `FieldSecurity` controls read and write with one value; missing options fail closed | `User.php:795-838` |
| V11 | Group roster API sends the full nested person row | `people-groups.php:313-339` |
| V12 | Cart-to-group checks permission only in the service and answers 400, not 403 | `cart.php`, `GroupService.php:87` |

### Suspected (needs a live check)

| ID | Suspicion | How to confirm |
|---|---|---|
| S1 | `Cache-Control: public` on person and family photos lets a shared proxy cache them | Request a photo through a caching proxy with two sessions |
| S2 | Email-log subjects on the timeline reveal finance activity to non-finance staff | Send a tax statement, view the timeline as zero-permission staff |
| S3 | `exportTo()` includes any relation already loaded on the object, so a future caller that loads notes before exporting would leak them | Unit test: load `getNotes()` then `exportTo('JSON')` |
| S4 | Saving a family with envelopes hidden in settings resets `fam_Envelope` to 0 | Set `bUseDonationEnvelopes` off, save a family that has an envelope |

### Private

One finding is withheld from this public report and reported to the maintainer privately.

## 11. Negative tests to write before implementation

One user per grant, as RFC 0002 lesson 6 requires. Each test names its entrypoint.

| Field class | Entrypoint category | Scenario | Expected |
|---|---|---|---|
| T4 membership date | Editor | EditRecords-only user posts `MembershipDate` | Value unchanged once `membership.date.manage` exists; today it changes (baseline test records that) |
| T4 membership date | Import | Admin import as a template without the grant | Rejected or skipped with a report |
| T4 classification | Kiosk | Guest registration request carries a `ClassificationId` | Ignored; the configured setting applies (regression for today) |
| T5 envelope | Editor | EditRecords-only user changes `fam_Envelope` | Rejected; today accepted |
| T5 envelope | API | Zero-permission user calls `GET /api/person/{id}` and `/api/groups/{id}/members` | No `Envelope` key |
| T5 scan data | API | Non-finance user calls `GET /api/family/{id}` | No `ScanCheck`, `ScanCredit`, `Envelope` (regression for today's behavior) |
| T3 birth year | API | Zero-permission user reads a person with hide age set | Policy decision: year absent, or documented as visible |
| Custom `bAll` | Profile, list, API | Zero-permission user | Field visible (fails today, V3) |
| Custom `bFinance` / `bNotes` / `bAdmin` | Profile, list, API, editor, every report, label, search | User without the matching flag | Field name and value absent everywhere |
| Custom unknown option | All | Field pointing at a missing option | Hidden from non-admins |
| Custom write | Editor | User without the field's flag saves the record | Restricted value unchanged |
| Group-specific field | Profile, group report | Zero-permission staff | Absent unless a group-scoped grant exists |
| T6 private note | Timeline, note API, NoteEditor | Notes user who is not the author | Omitted on the timeline; 403 on direct ID |
| T1 photo | Photo API | Portal user requests a non-family member's photo | 403 or 404 |
| Labels | Label PDF/CSV | User without a print grant (after #10516) | Denied by the endpoint, not only the button |
| Restricted record (D1) | Every category | Ordinary staff by direct ID, search, list, label, report, roster | Absent, with no existence leak |
| Any | Portal | Portal user calls a staff API with an API key | 403 (regression for `AuthMiddleware.php:69`) |

## 12. Proposed RFC 0002 amendments (for decision)

1. **Field classes:** adopt the tiers in section 2 as the field-class vocabulary for `people.*` grants, with a minor overlay.
2. **Envelope numbers are finance identifiers** on every read and write path, or explicitly ordinary directory data. Pick one; today there are four rules.
3. **Presentation flags never count as protection** (hide age, hide wedding date, `prop_PersonDisplay`, `.no-export`). Add this to the invariants.
4. **Group-specific person fields** need a field class like custom fields before any group-scoped role ships.
5. **Device principals do not write governance fields.** Kiosk guest classification already comes from an admin setting; keep that rule for every device and service principal.
6. **One custom-field evaluator.** All callers go through one policy function that takes the stored option ID.

## 13. Rollout and rollback

1. Baseline tests that record today's behavior, including V2–V5, before any change.
2. Fix the evaluator mismatch together with its negative tests in one change, so the visibility flip is reviewed as a security change.
3. Add field classes and the crosswalk as non-enforcing data with an upgrade report that lists every field mapped to `restricted.admin`, `pastoral` or deny.
4. Enforce per surface (API serializers first, because they feed browser tables), following S1 in RFC 0002: no confidentiality claim until every path in sections 3–5 is covered.
5. Rollback restores the legacy evaluator only. It must not widen any field that was restricted before the change.

Security gates: independent review on step 2 and step 4; maintainer approval before any field class is marked enforced.
