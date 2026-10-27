# RBAC audit #10519: church administrator access-control journeys

**Status:** product and security requirements audit, for maintainer review. Not for merge. No runtime change is proposed or authorized here.
**Issue:** [#10519](https://github.com/ChurchCRM/CRM/issues/10519) · **Epic:** [#10922](https://github.com/ChurchCRM/CRM/issues/10922) · **Builds on:** [#10515 report](10515-person-family-fields.md), [#10516 report](10516-export-print-datatables.md) · **Design:** [RFC 0002](../../decisions/0002-rbac-redesign.md), decisions in [#10920](https://github.com/ChurchCRM/CRM/pull/10920)
**Code baseline:** `master` at [`914c437`](https://github.com/ChurchCRM/CRM/tree/914c437b8e87da5db47a2b36419b004407596214). Links are pinned to that commit.

## Bottom line

1. **An administrator can answer "who can do what" only per user, only in legacy flags, and only for writes.** The user editor offers three modes (Administrator, Self-service, Custom) plus ten flags and a list of `userconfig_ucfg` rows. Reading people, families, reports, labels and the list exports is always granted to every staff account and cannot be narrowed.
2. **There are no roles.** No templates, no assignment of a role to several users, no scope beyond Volunteer v2 ministry scopes. One person holding several church jobs means ticking more flags.
3. **Revocation works on the next request for browser sessions.** The session stores only the user ID and reloads the user each request; deleting the user, or marking the person inactive or deceased, ends the session. An admin setting a new password does not end the target's other sessions.
4. **The takeover paths that P6 names are open and quiet.** An admin can set another user's password, disable their 2FA, or regenerate their API key and read it from the response. Password set leaves a timeline note; 2FA disable leaves nothing; none notifies the account holder. The 2026-10-27 decision (second approver plus notification for sensitive-grant holders) is not implemented yet.
5. **No last-administrator safeguard exists in the user editor.** An admin can remove their own admin flag. Deactivating or marking deceased the last admin is blocked; demoting them is not.
6. **The audit trail is the person timeline.** User created, updated, password reset, API key regenerated: each is a note of type `user` on the linked person, visible to every staff account. Exports, 2FA changes, permission diffs and kiosk changes are not recorded.
7. **Kiosks are managed by `ManageGroups`, not by administrators**, and are revoked by deleting the device. The cookie, roster and field audit stays with #10518.
8. No new exploitable bug was found in this audit beyond the private finding already reported under #10515/#10516.

## Method and limits

- Source reading only at the baseline commit; no live install, no screenshots. Screenshots for the journeys should come from the Playwright pipeline once the to-be UI exists.
- "As-is" describes shipped code. "To-be" rows are proposals for George and do not describe current behavior.
- F-03 (email and recovery) is closed and not reopened. Member sign-in (#10464) is accepted for 7.9.0 but not shipped; where it changes a journey, that is noted.

## 1. What an administrator can configure today

| Control | Where | Gate | Effect | Source |
|---|---|---|---|---|
| Bootstrap admin | Install seed: user `Admin`, `usr_NeedPasswordChange = 1` | — | First sign-in must change the password | [`Install.sql:817-825`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/mysql/install/Install.sql#L817-L825) |
| Create user for a person | `/admin/system/users/new` | Admin | Random password, welcome email when email is set up | [`system.php:831-832`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/admin/routes/system.php#L831-L832) |
| Access mode | User editor | Admin | Administrator, Self-service (portal only), or Custom | [`UserService.php:211-233`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Service/UserService.php#L211-L233) |
| Module flags | User editor, Custom mode | Admin | Add/Edit/Delete records, Manage groups, Finance, Fundraisers, Ministries, My ministries, Notes, Add events | [`UserService.php:238-253`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Service/UserService.php#L238-L253) |
| `userconfig_ucfg` rows | User editor config table; `SettingsIndividual.php` | Admin; the user too when the row's permission is `TRUE` | `bEmailMailto`, `bAddEvent` and any admin-added row. **`ucfg_permission` is the grant**; `ucfg_value` is ignored by `isEnabledSecurity()` | [`User.php:829-833`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L829-L833), [`Install.sql:750-753`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/mysql/install/Install.sql#L750-L753) |
| Custom-field security | Person/Family custom field editors | Admin | One `FieldSecurity` value per field for read and write | #10515 §6 |
| Property, list and group-role catalogues | Admin pages and `/admin/api` | Admin | Configuration only | RFC 0002 §2 lessons |
| Volunteer v2 scopes | `/api/ministries/scopes` | Ministry admin; `canGrantScope()` checks the actor's own ceiling | Scoped coordinators and team leaders | [`VolunteerAuthorizationService.php:151`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Volunteer/Service/VolunteerAuthorizationService.php#L151) |
| Password set, reset email, 2FA disable, unlock, delete | Users list action menu → `/admin/api/user/{id}/*` | Admin | See journey J9 | [`user-admin.php:17-176`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/admin/routes/api/user-admin.php#L17-L176) |
| Another user's API key | `POST /api/user/{id}/apikey/regen` | Self or admin | Returns the new key to the caller | [`api/routes/users/user.php:13-28`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/api/routes/users/user.php#L13-L28), [`UserMiddleware.php`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Slim/Middleware/Api/UserMiddleware.php) |
| Read-only permission view | `/v2/user/{id}` Permissions tab | Self or admin | Lists flags; states "All users can view congregation members. This permission cannot be removed." | [`v2/templates/user/user.php:399-440`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/v2/templates/user/user.php#L399-L440) |
| Kiosk registration window, accept, assign, rename, reload, delete | `/kiosk/admin`, `/kiosk/api/*` | **ManageGroups** | Device principal lifecycle | [`kiosk/routes/admin.php:50`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/kiosk/routes/admin.php#L50), [`kiosk/routes/api/kiosks.php`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/kiosk/routes/api/kiosks.php) |
| Kiosk guest classification | `iKioskGuestClassification` setting | ManageGroups page shows it | Applied to every kiosk guest | [`device.php:645-648`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/kiosk/routes/device.php#L645-L648) |
| Plugins install/activate/configure | `/plugins/management` | Admin | Plugin routes then get auth only, no role | [`plugins/index.php:46-52`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/plugins/index.php#L46-L52) |
| 2FA mandate, lockout, session timeout | System settings `bRequire2FA`, `iMaxFailedLogins`, `iSessionTimeout` | Admin | Applies to everyone | [`LocalAuthentication.php:307-345`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Authentication/AuthenticationProviders/LocalAuthentication.php#L307-L345) |
| Backup, restore, CSV export, import, logs | Admin pages | Admin | Whole-database access | #10516 §4 |

Not configurable today: who may read people/families, print, export, run reports, see a child, see membership date, or see envelope numbers; restricted people; role templates; temporary or expiring access; per-group or per-class leader access outside Volunteer v2.

## 2. Administrator journeys

Each journey: as-is flow with sources, gaps, and the to-be acceptance for RBAC.

### J1. First install

- **As-is:** sign in as `Admin` with the seeded password, forced change on first sign-in ([`LocalAuthentication.php:330-334`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Authentication/AuthenticationProviders/LocalAuthentication.php#L330-L334)); the Get Started page walks church details. There is no "who sees the directory" choice: all staff read everything.
- **Gaps:** no review of effective access before going live. **Suspected:** the seeded default `bEmailMailto` row carries permission `TRUE`, so a user may gain email-list access the first time their config rows are cloned from the defaults; confirm on a fresh install.
- **To-be:** a setup step that names the bootstrap administrator, offers the P5 default (read-only ordinary directory for new staff), shows that no board, pastoral or voting grant exists, and links to role templates. No automatic grants.

### J2. Invite a user

- **As-is:** pick a person, choose a mode, tick flags, save. Welcome email if email is configured. Portal access today means a `user_usr` row with Self-service mode.
- **Gaps:** no role templates; flag names do not say what data they reveal ("Edit Records" also allows membership date and family envelope edits, #10515 V2/V4); read is not shown as a choice.
- **To-be:** pick one or more templates (P8, P11) with an effective-access preview before save (view / edit / print / filtered export / bulk export / administer, RFC 0002 §3 item 5). After member sign-in ships, portal members need no user account at all.

### J3. Assign or change a role

- **As-is:** edit flags. Saving writes a timeline note "system user updated" with no before/after ([`UserService.php:436`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Service/UserService.php#L436)). No ceiling: any admin can grant any flag, including admin, to anyone or themselves. Changes apply on the target's next request.
- **Gaps:** no grant diff, no reason, no second approver for sensitive grants, no last-admin guard ([`UserService.php:390-439`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Service/UserService.php#L390-L439)).
- **To-be:** S4/T2: grantable ceiling, diff preview, audit event with before/after grants, last-admin protection, step-up for sensitive grants.

### J4. Restrict a person

- **As-is:** not possible. Inactive and deceased are lifecycle flags; they hide people from default lists and labels but not from profiles, search or the API (#10515 §3.1).
- **To-be:** D1: pastor or designated pastoral leadership sets "restricted" (P7); restricted people disappear from every path in the #10515 and #10516 inventories before the feature is advertised (S1).

### J5. Restrict a custom field

- **As-is:** set Field Security on the custom field editor. One value for read and write. Ordinary staff currently see no custom fields on the profile and list at all, whatever the setting, because the ORM evaluator never matches (#10515 V3).
- **Gaps:** the setting's options are permission names (`bFinance`, `bNotes`), not data classes; no preview of who will see the field.
- **To-be:** field classes from #10515 §2 with separate read and write, crosswalk from #10515 §9, and a "who can see this field" preview.

### J6. Allow (or stop) export

- **As-is:** only the dedicated CSV export can be withheld (admin only). People list CSV/print, People Reports CSV, labels and the directory PDF are open to every staff account (#10516 §3–§4).
- **To-be:** the capability and dataset matrix in #10516 §7, enforced at endpoints and feeds, with export audit events.

### J7. Create a kiosk

- **As-is:** a `ManageGroups` user opens the registration window, the tablet registers itself with a cookie, the user accepts it and assigns an event ([`kiosks.php:82-208`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/kiosk/routes/api/kiosks.php#L82-L208)).
- **Gaps:** kiosk management is not an administrator task and not a separate grant; no audit of accept/assign/delete.
- **To-be:** `kiosk.devices.manage` and `kiosk.assignments.manage` (RFC 0002), audited, with the #10518 cookie and roster checks.

### J8. Review effective access

- **As-is:** open each user's Permissions tab, or scan the Access column (Admin / Self-service / Custom) on the users list. No "who can see X" view, no export of grants, no list of who holds Finance or Notes.
- **To-be:** per-user preview with grant sources (S9), and a per-capability "holders" list, marked partial while legacy pages remain.

### J9. Revoke access, staff departure

| Action | As-is effect | Source |
|---|---|---|
| Delete the user | Session ends on the next request (user no longer loads); config rows deleted; optional email | [`user-admin.php:129-165`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/admin/routes/api/user-admin.php#L129-L165), [`LocalAuthentication.php:289-299`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Authentication/AuthenticationProviders/LocalAuthentication.php#L289-L299) |
| Mark the person inactive or deceased | Sign-in, sessions and API key blocked; last sign-in-capable admin protected | [`AuthenticationManager.php:243-262`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Authentication/AuthenticationManager.php#L243-L262), [`UserService.php:98-114`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/Service/UserService.php#L98-L114) |
| Remove flags | Next request uses the new flags | `LocalAuthentication.php:76-86` |
| Reset password by email | Random password, `NeedPasswordChange`, API key rotated; open sessions are sent to the change-password page | [`user-admin.php:45-80`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/admin/routes/api/user-admin.php#L45-L80) |
| Admin sets a password | API key rotated; **other sessions continue**; timeline note only | [`User.php:1173-1179`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L1173-L1179) |
| Disable 2FA | Secret and recovery codes cleared; **no note, no notice** | [`User.php:1001-1011`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L1001-L1011) |
| Temporary access, leave of absence | Not supported; admins toggle flags by hand | — |

### J10. Recover from a lost device

- **Staff phone or laptop:** no session list and no "sign out everywhere". The admin can reset the password by email (sends open sessions to the change-password page) or deactivate the person. A stolen API key is revoked by any password change or by regenerating the key.
- **Kiosk:** delete the device in the kiosk manager; its cookie stops matching.
- **To-be:** per-user session revocation, API key list with expiry (RFC 0002 §8), kiosk revoke without outage (#10518).

### J11. Audit an event

- **As-is:** the person timeline shows `user`-type notes (created, updated, password reset/changed, API key regenerated, login reset) to every staff account ([`User.php:761-793`](https://github.com/ChurchCRM/CRM/blob/914c437b8e87da5db47a2b36419b004407596214/src/ChurchCRM/model/ChurchCRM/User.php#L761-L793)). Application and auth logs are on the admin Logs page. No record of exports, 2FA disable, permission diffs, kiosk actions or custom-field security changes.
- **Gaps:** account-security notes are visible to ordinary staff; there is no admin-only, tamper-evident audit view.
- **To-be:** an audit log for security events (grant changes with diff, takeover-capable actions, exports, kiosk lifecycle, field-class changes) readable by a dedicated grant, never containing record content.

## 3. Persona × task × dataset × action × scope × approval

"Today" is the closest current flag set. "Proposed" follows RFC 0002 templates. Approval: who may grant it.

| Persona | Task | Dataset | Action | Scope | Today | Proposed grant | Approval to grant |
|---|---|---|---|---|---|---|---|
| Church administrator / IT | Configure system, users | Configuration | Administer | Install | Admin flag (also reads everything) | Church Administrator template, no confidential content (P6) | Another administrator; last-admin guard |
| Office manager | Update people and families | Ordinary directory | R/W | Ordinary records | Edit/Add Records | Office Staff / People Editor | Administrator |
| Receptionist | Look up contact, print one record | Directory, contact | R, limited print | Ordinary | Zero-flag staff (but can also bulk export) | Read-Only Staff | Administrator |
| Membership officer | Membership date, classification, reports | Governance | R/W, filtered export | Ordinary | Edit Records (no separate grant) | Membership Officer; voting eligibility only with its own grant (P10) | Administrator + pastor (D8) |
| Pastor / pastoral care | Pastoral notes, restrict people | Notes, restricted marker | R/W shared; classify | Ordinary + restricted by grant | Notes flag; admin reads all private notes | Pastor template; P7 classify | Pastoral leadership designee (D7) |
| Treasurer / finance | Giving, deposits, envelopes | Finance | R/W, export | All givers | Finance flag | Finance Manager / Assistant | Administrator + second approver for export (D2) |
| Volunteer coordinator | Schedules, assignments | Volunteer | R/W | Coordinated ministries | Manage My Ministries + scopes | Ministry Coordinator (reuse `VolunteerAuthorizationService`) | Ministry admin within ceiling (exists) |
| Group / team leader | Roster, attendance, messages | Group roster | R, message | Own groups | Manage Groups (all groups) | Group Leader scoped to assigned groups | Administrator or group owner |
| Sunday school teacher | Class roster, attendance | Children | R, limited print | Own class | Manage Groups (all) or nothing | Class leader scoped; `minors.export` scoped | Children's ministry lead |
| Check-in volunteer | Check in/out, pickup | Event roster | R names, W attendance | Assigned event | Kiosk device or Add Events | Check-In Volunteer / kiosk device | Kiosk manager |
| Parent / guardian | Update child info | Own children | R/W approved fields | Linked children | Portal adult of household (role-based) | Guardian (D3) | Staff verification |
| Member | Update own profile | Self, household | R/W allowlist | Self | Portal (Self-service user) | Member / Household portal; after #10464 no account | Self sign-in rules |
| Visitor | Register | Self | Create | Self | Public register, kiosk guest | Same, review queue | — |
| Board member | Governance meetings | — | — | — | No grant (correct) | No grant from board membership (P10) | — |
| Integration / service | Sync emails, calendar | Scoped | Scoped | Token scope | API key = full user | Service principal with scopes (RFC 0002 §8) | Administrator, audited |
| Kiosk | Check-in | Event roster | Scoped | Assigned event | Device cookie | Device principal (#10518) | Kiosk manager |

### Least-privilege demonstrations (proposed preview output)

| Persona | Preview shows | Not granted |
|---|---|---|
| Receptionist | View ordinary people and families; print one record | Edit, filtered or bulk export, labels for the whole church, children export, finance, notes |
| Sunday school teacher (class 3) | View class 3 roster, attendance write, class 3 class list print | Other classes, whole directory export, parents' finance |
| Small church: one person as office manager + treasurer | Two complete grants: People Editor (ordinary) and Finance Manager (finance) | No cross-product: finance export does not include pastoral fields; People Editor does not include membership date |

## 4. UI and UX gap inventory

| Gap | Where | Kind |
|---|---|---|
| Read access is "always granted" and cannot be narrowed; print/export not shown at all | Permissions tab, user editor | Missing control |
| "Edit Records" silently covers membership date, classification and family envelope | User editor labels | Confusing name |
| `userconfig_ucfg` "permission" column is the grant; "value" looks like the setting but is ignored for security | User editor config table | Hidden legacy flag |
| `FieldSecurity` options are flag names, not data classes; no preview | Custom field editors | Confusing name, missing preview |
| No role templates or multi-user assignment | Users | Missing feature |
| No grant diff, reason, or second approval | User editor | Weak default |
| No last-admin guard on demotion | User editor | Irreversible without database access |
| 2FA disable leaves no trace and sends no notice | Users action menu | Missing audit |
| Account-security notes visible to all staff on the timeline | Person timeline | Over-disclosure |
| Kiosk management sits under Manage Groups | Kiosk manager | Wrong owner |
| Permissions tab says administrators "have full access to all features and data" | `/v2/user/{id}` | Will conflict with P6 once enforced |
| No session list, no sign-out-everywhere | My account / users | Missing control |
| Accessibility, mobile, localization: users list and editor use Tabler cards and translated strings; the flag list is long on phones and has no grouping by risk | User editor | UX |

## 5. Policy decisions for George

1. **New-install default (P5):** read-only ordinary directory for new staff, with limited print only, or also filtered export as today?
2. **Administrator vs confidential (P6):** confirm admins keep configuration and user management but no pastoral notes, `pastoral`/`restricted.admin` custom fields, or bulk export by default; confirm the takeover controls apply to API key regeneration as well as password set and 2FA disable.
3. **Delegation:** may a non-admin (e.g. pastor, finance manager) grant their own template to others within a ceiling, as Volunteer v2 already does?
4. **Export/print controls:** adopt the #10516 §7 matrix; decide upgrade parity (keep today's access and ask admins to narrow) vs narrow on upgrade.
5. **Membership and voting (D8):** who grants Membership Officer; whether voting eligibility needs a second approver.
6. **Guardian boundary (D3):** whether household "adult" stays the portal rule until guardian verification exists.
7. **Kiosk and service principals:** move kiosk management to its own grant; API keys get scopes and expiry before any integration is promoted.
8. **Migration:** map each existing user to suggested templates from effective V1 access, never auto-assign (RFC 0002 coexistence milestone); report fields that map to deny.
9. **Audit visibility:** move account-security events off the public person timeline into an admin audit view?

## 6. Threat and regression scenarios

Grounded in the advisory classes RFC 0002 already lists publicly; no embargoed detail.

| Class | Scenario | Negative test |
|---|---|---|
| Direct URL / IDOR | Ordinary staff opens a restricted person by ID, via `/people/view/{id}`, `/api/person/{id}`, roster, label, report | Same denial everywhere, no existence leak |
| Static files | Request `Images/Person/{id}.jpg` directly | 403 (regression for GHSA-r286-h493-ppwj) |
| Client-side overfetch | Read-only staff loads the group roster feed | No fields outside the table's DTO (#10516 E2) |
| CSRF | Cross-site POST to user editor, password set, 2FA disable, kiosk accept | Rejected (CSRF token / same-site) |
| XSS | Person or family name with markup in user list, labels, kiosk | Escaped (regression for GHSA-mrr9-jxqx-xv62 class) |
| Import/export | Admin import sets membership date or a restricted custom field without the field grant (after RBAC) | Skipped with a report |
| Field ACL | User without a field's class reads it in any output | Absent (#10515 §11) |
| Role escalation | Non-admin edits own flags via `/admin/api/user/{id}` or user editor POST | 403 |
| Role escalation | Admin removes own admin flag when last admin | Rejected with explanation (to-be) |
| Takeover | Admin regenerates another user's API key | To-be: second approver for sensitive-grant holders, notice to holder, audit |
| Takeover | Admin disables 2FA for a pastor | To-be: second approver, notice, audit event |
| Revocation | Flag removed during an open session | Next request denied (regression) |
| Revocation | Person deactivated with an open API key | Next API call 401 (regression) |
| Kiosk cookie | Kiosk cookie sent to `/api/person/{id}` or `/admin/*` | 401 (#10518) |
| Auth / 2FA | Public API login with 2FA enabled | 2FA still required (regression for GHSA-8cwr-x83m-mh9x class) |
| Portal | Portal user calls staff APIs with an API key | 403 (regression, `AuthMiddleware.php:69`) |
| Plugin | Read-only staff calls a plugin route that lists people data | Denied unless the plugin declares a permission (#10516 E5) |

## 7. Roadmap shape

Follow-up issues are listed separately for approval (not in this report). They fall into four bands:

1. **Baseline tests first** (single-grant users across UI, API, portal, export, kiosk).
2. **Safeguards that do not need RBAC:** last-admin guard, audit trail for takeover-capable actions and 2FA disable, notices to the account holder.
3. **Design inputs for #10923:** template catalog with the persona table above, field classes, export capabilities, delegation ceiling, audit event schema.
4. **RBAC phases** per RFC 0002 F0–F4, after #10923 is approved.
