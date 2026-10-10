# Member sign-in: every member can sign in to the Member Portal

Status: proposal for maintainer feedback on #10463. Code citations are `path:line` on upstream `master` at `bbe5e1642`. Nothing is built, and no child issues are filed.

## Summary

The Member Portal (#8977, #10246) gives members their own profile, family, calendar and volunteering. But a member can only use it after staff create an account for them, one person at a time, and deliver a password. This proposal removes that step:

- **Every person has a sign-in status.** No staff member creates member accounts.
- **The church decides who may sign in.** It ticks classifications, sets a minimum age, and turns on one switch.
- **A member signs in with their own email address.** The first time, they use "First time signing in, or forgot your password?" and set a password through an emailed link. No password is ever sent by email.
- **Staff can stop any person signing in** with "Disable login", whatever that person's permissions.
- **Staff permissions are managed from the person's page.** Admin → Users becomes the list of staff accounts.
- **The user interface says "Member Portal" where it says "self-service" today.**

The account table stays as it is. `user_usr` already uses the person's id as its key (`src/mysql/install/Install.sql:771-806`), so "merging" Person and User needs no new table. The account row is created when an eligible person first sets a password.

## Questions for the maintainer

1. **Account table (D1).** Keep `user_usr`, and create a row when a person first sets a password, rather than moving the 45 account columns onto `person_per`? The physical merge would touch `IAuthenticationProvider::getCurrentUser(): ?User`, 359 `getCurrentUser()` calls in 155 files, and 48 Cypress files.
2. **Read-only staff (#9003, D12).** Keep the read-only staff account (an account with every permission off), but stop offering it as the form's default? Or retire it, so that an account with no permissions is "Member Portal only"?
3. **Sign-in emails go only to the person's own email, never to the family email (D4, D7).** This changes things for existing accounts whose person has no email of their own. Is that acceptable?
4. **`bRequire2FA` applies to staff accounts only (D13).** Should the existing setting change meaning, or should we add a separate setting?
5. **Order of work (D14, §11).** #10197 (throttling and temporary lockout) lands first, and the reset-link fix (§6) goes in a stand-alone bug PR before the feature. Is that the right order?
6. **Invitations as a timer job (D9).** Is it acceptable to add one job to `SystemService::runTimerJobs()` (`src/ChurchCRM/Service/SystemService.php:111`) that sends queued invitations within an hourly limit?
7. **Teenagers (D17).** In the portal, an "adult of the family" must be 18 or older, and only adults may confirm the family's details. This narrows #9865, where confirming is open to every family member. Is that acceptable?
8. **Release and migration name.** Which release should this target? Would `7.9.0-member-sign-in.sql` be the right migration name?

## 1. Goals and non-goals

**Goals**
- Eligible members reach the portal with no staff work.
- Staff set a rule for who may sign in; they don't create accounts.
- Any person can be stopped from signing in with one checkbox.
- It works without SMTP: staff can hand over a setup link.
- No upgraded church opens sign-in to everyone without choosing to.

**Non-goals**
- Moving the account columns onto `person_per` (see §12).
- A member directory in the portal (Member Portal design §5.6).
- Sign-in through Google, Microsoft or another single sign-on provider.
- Sign-in without a password, such as an emailed link or a text message.
- Per-person exceptions to the rule (a church changes the person's classification instead).

## 2. Decisions

| ID | Decision |
|---|---|
| D1 | Person and User are merged in the user interface, not in the tables. `user_usr` stays the account table, keyed by `per_ID`. A row is created when an eligible person first sets a password. |
| D2 | A master switch, "Member sign-in" (`bMemberSignIn`), on Admin → Member Portal. It is **off** by default, both after an upgrade and on a new install. With it off, nobody can set up their own account; staff give sign-in to one person at a time, as today. |
| D3 | Who may sign in: a set of blocks that apply to every account (§4.1), plus a member rule. The member rule needs a ticked classification and a minimum age (default 13). When the birth year is unknown, the family role must be head or spouse. Staff accounts skip the member rule. |
| D4 | People sign in with their own email (`per_Email`) or with an existing username. The work email and the family email never count. |
| D5 | A personal email that two people who can sign in both use can't be used to sign in. Those people are listed under "Needs attention", and an existing username still works. |
| D6 | A person with no personal email can't set up their own account. Staff add an email, or hand over a setup link. |
| D7 | The setup and reset link opens a Set Password form, and only submitting the form uses up the token. No password is ever emailed. Tokens are stored hashed, used once, and revoked when a newer one is sent. The answer to a request is the same whether or not the person exists. |
| D8 | Without SMTP, the person's page offers "Copy setup link", and Admin → Member Portal warns that members can't set themselves up. |
| D9 | "Invite eligible people" emails a setup link to everyone eligible and not yet set up, within an hourly limit (`iSignInInvitesPerHour`, default 100). |
| D10 | "Disable login" is a new `per_LoginDisabled` field. It becomes a third reason in `User::getSignInBlockedReason()`, so every existing check enforces it. |
| D11 | The person's page has a "Sign-in and permissions" card. Admin → Users lists staff accounts only, and its button reads "Add New Admin". |
| D12 | An account created by setup is "Member Portal only" (`usr_EditSelf = 1`), with no API key and no username. The admin form no longer starts with every permission off. |
| D13 | `bRequire2FA` applies to staff accounts. Members may enroll if they want to. |
| D14 | #10197 (throttling, temporary lockout, uniform responses) is a prerequisite. A member never needs an administrator to unlock their account. |
| D15 | Changing the email of a person who has an account follows rules (§8). A username equal to the old email is cleared. |
| D16 | Only an administrator may delete a person who has staff permissions. A member account goes with the person it belongs to. |
| D17 | Teenagers: a member under 18 can sign in (when the minimum age allows it) but is never an "adult of the family" in the portal. Confirming the family's details is for adults only. |
| D18 | Only the visible text is renamed: "Self-service only" becomes "Member Portal only". Code identifiers stay as they are. |

## 3. Today, and what changes

| Area | Today | Proposed |
|---|---|---|
| Who has an account | Only people staff create one for: Admin → Users → New, or Make User (`src/people/routes/view.php:98-99`) | Everyone the rule allows, once they set a password |
| Sign-in name | `usr_UserName` only (`LocalAuthentication.php:150`, `src/api/routes/public/public-user.php:66`). The form says "Email address" (`src/session/templates/begin-session.php:91`), which only works because the user form copies the email into the username once (`src/admin/routes/system.php:1058`) | Personal email or username |
| First password | Random, sent in plain text by `NewAccountEmail`; nothing when email is off (#9696) | The member sets it from a link |
| Reset | Lookup by username. The link is used up on GET and the user is emailed a random password (`src/session/routes/password-reset.php:23-66`) | Lookup by email or username. The link opens a form; the user chooses the password |
| Blocks | Deceased or inactive person (#10193, `User.php:680-698`) | Adds: login disabled, family deactivated, awaiting review, and the member rule |
| Default new account | Every permission off, which gives read access to all people and families (#9003, `system.php:1072-1077`) | Member accounts are confined to the portal; the staff form has no preset |
| Permissions | Admin → Users → edit | The person's page, administrators only |
| 2FA mandate | All users (`SystemConfig.php:332`) | Staff accounts |
| API key | Every account (`User.php:588-592`) | Staff accounts only |

## 4. Who may sign in

### 4.1 Blocks for every account

A person can't sign in, or set up an account, when any of these is true:

| Reason | Source | New? |
|---|---|---|
| `deceased` | `per_DateDeceased` | no (#10193) |
| `inactive` | `per_DateDeactivated` | no (#10193) |
| `disabled` | `per_LoginDisabled` (D10) | yes |
| `family_inactive` | the person's family has `fam_DateDeactivated` | yes |
| `awaiting_review` | `per_NeedsReview` (set by public registration `src/api/routes/public/public-register.php:146,287`, kiosk walk-ins `src/kiosk/routes/device.php:513`, and members a family adult adds in the portal `src/ChurchCRM/Portal/PortalSelfService.php:384`) | yes |

These extend `User::getSignInBlockedReason()`, so they are enforced everywhere #10193 already checks:
- the password step and the 2FA step;
- every session request;
- API-key authentication;
- `POST /api/public/user/login`;
- both reset requests, and the use of a token.

The new checks need one extra join to the family in the query the method already runs.

### 4.2 The member rule

While `bMemberSignIn` is on, only people in a ticked classification can sign in to the Member Portal. That covers people setting up an account and member accounts that already exist. These checks apply only to member accounts, meaning `User::isEditSelfExclusive()`:

- **Classification:** the person's classification is ticked in `aMemberSignInClassifications`. "No classification" is a row of its own.
- **Age:** the person is at least `iMemberSignInMinAge` years old (default 13).
  - Age comes from the birth date even when the person's age is hidden. `Person::getNumericAge()` returns 0 for a hidden age, so it can't be used (`Person.php:938-950`).
  - When the birth year is unknown, the person's family role must be head (`sDirRoleHead`) or spouse (`sDirRoleSpouse`).

**Staff accounts skip the member rule.** A staff account is any account that isn't a member account: administrators, accounts with any permission, and read-only staff. The blocks in §4.1 still apply to them.

**While the switch is off:**
- Existing accounts work as they do today, apart from the new blocks in §4.1.
- Nobody can set up their own account.
- Staff can still hand a setup link to one person at a time (§7.1).

**No per-person exception.** A church that wants one person outside the rule changes that person's classification.

### 4.3 Usable email

To set themselves up with an email, a person also needs a usable personal email:
- `per_Email` is not empty;
- no other person who can sign in has the same address (D5).

The comparison trims spaces and ignores case; the column's collation already ignores case.

## 5. Signing in

### 5.1 Finding the person

The login form's field becomes "Email or username". The value is trimmed, then matched in this order:

1. accounts whose `usr_UserName` equals it;
2. people whose `per_Email` equals it and who can sign in or set up an account (§4).

If both sets point at the same person, that person is the match. If they point at two or more different people, sign-in is refused with the same message as a wrong password, and the pair is listed under "Needs attention". The same lookup serves the login form, `POST /api/public/user/login`, and the reset and setup request.

### 5.2 After a successful match

- **Member accounts** land in the portal (`AuthenticationManager.php:299-307`, unchanged).
- **The public login API refuses member accounts**, with the same message as a wrong password. It returns API keys only to staff accounts.
- **Sessions:** the session keeps an HMAC of the stored password hash, keyed with an existing application key. When a password is set or reset, the account's other sessions end on their next request. Today only the API key rotates.

## 6. First password and reset

One flow serves "set up my account" and "I forgot my password".

### 6.1 Request

- The login link reads "First time signing in, or forgot your password?". It is shown when `bEnableLostPassword` is on and email is configured.
- The page posts `{identifier}` to `POST /api/public/user/password-reset`. The old `{userName}` field still works.
- The legacy `POST /session/forgot-password/reset-request` takes the same lookup and gives the same answer.
- **The answer is always the same:** "If that address belongs to someone who can sign in, we have emailed a link." Requests are throttled per IP and per identifier (#10197).
- **What is sent:**
  - The person has an account: a reset link, type `password`, valid 24 hours.
  - The person has no account, `bMemberSignIn` is on, and the person passes §4: a setup link, type `password`, valid 24 hours.
  - Anyone else: nothing.
- **Sign-in emails go only to `per_Email`, never to the family email.** A family email is shared by the household. This also applies to `LockedEmail`, `UnlockedEmail` and the email-changed notice (§8).

### 6.2 The link

- **`GET /session/forgot-password/set/{token}` shows a Set Password form.** The GET doesn't use up the token, so mail scanners that open links do no harm. Today they use it up (`password-reset.php:23-66`).
- **Submitting the form:**
  1. Uses up the token atomically: `UPDATE … SET remainingUses = remainingUses - 1 WHERE token = ? AND remainingUses > 0 AND valid_until_date > NOW()` must change exactly one row.
  2. Checks §4 again.
  3. Applies the password policy (`iMinPasswordLength`, `aDisallowedPasswords`, no parts of the person's name).
  4. Creates the account row if there isn't one (D12).
  5. Sets the password and clears failed logins.
  6. Revokes the person's other tokens.
  7. Ends the person's other sessions.
  8. Sends the person to the login page with their email already filled in.
- **What goes away:**
  - `ResetPasswordEmail`, which carried a plain-text password, is removed.
  - `NewAccountEmail` stops carrying a password and links to the same form instead.
  - Admin → Users → Reset Password sends a reset link.
  - The administrator's "Change Password" stays for staff accounts, and now applies the password policy on the server.

### 6.3 Tokens

- **Existing table:** `tokens` (`Install.sql:949-956`).
- **Hashed:** the `token` column stores SHA-256 of the secret, and the link carries the secret.
- **Expiry:** `valid_until_date` becomes `TIMESTAMP` in `orm/schema.xml`. The SQL column is already `datetime`.
- **New type `signInSetup`:** valid 7 days, used once. Staff setup links (§7.1) and invitations (§7.3) use it.
- **Revocation:** a new `password` or `signInSetup` token for a person deletes that person's older ones.
- **Same id for both:** `reference_id` is the person id, which is also the account id (`usr_per_ID`).
- **The migration hashes outstanding `password` tokens in place** (`SHA2(token, 256)`), so links already sent keep working.
- **Out of scope:** family verification tokens (`verifyFamily`) keep their current behaviour.

### 6.4 Without SMTP

- **Problem:** `SystemConfig::isEmailEnabled()` needs a configured SMTP host (`SystemConfig.php:627-634`), and core sends only over SMTP (`src/ChurchCRM/Emails/BaseEmail.php:54-71`). A fresh install therefore has no reset and no setup by email.
- **Fallback:** staff can use "Copy setup link" (§7.1).
- **Warning:** the Sign-in tab warns that members can't set themselves up until email is configured.

## 7. Staff tools

### 7.1 Person page: "Sign-in and permissions" card

The card replaces the "Make User" button (`src/people/routes/view.php:98-99`).

| Shows | Detail |
|---|---|
| Status | Active · Not set up · Locked · Disabled · Not eligible (with the reason, e.g. "Classification Visitor is not ticked") · Needs attention (shared email) |
| Sign-in name | Username if set, otherwise the personal email |
| Last sign-in | `usr_LastLogin`, sign-in count |

| Action | Who |
|---|---|
| Disable login / Allow login | Administrators for anyone; Edit Records for people without staff permissions. The last administrator who can sign in can't be disabled. |
| Send setup email · Copy setup link | Same split. "Copy setup link" shows the link once, valid 7 days. |
| Unlock | Same split |
| Permissions (Administrator / Custom / Member Portal only) | Administrators only, with today's user-editor rules |

Every action writes a timeline note on the person, as account changes already do (`User::createTimeLineNote()`).

### 7.2 Admin → Member Portal → Sign-in tab

This is a new tab next to Settings, Themes, Statistics and Calendars (`src/admin/views/member-portal.php:69-93`). It contains:

- **Member sign-in switch** (`bMemberSignIn`). It can't be turned on until at least one classification is ticked.
- **Who may sign in:** every classification in the classification list, plus "No classification". Each has a checkbox and a count of active people. Nothing is ticked at first.
- **Minimum age**, default 13. Help text explains that some countries require 16 for children to consent to an online account.
- **Email warning** when email isn't configured (§6.4).
- **People who can sign in:** a searchable table with a status filter (Active, Not set up, Locked, Disabled, Needs attention).
- **Needs attention:** shared personal emails, and usernames that match another person's email (§5.1).
- **Invite eligible people** (§7.3).

These settings are defined in `SystemConfig::buildConfigs()` and left out of `buildCategories()`, like the other portal settings (`SystemConfig.php:266-279`).

### 7.3 Invitations

- **"Invite eligible people"** shows how many people are eligible, have a usable email, have no account yet, and weren't invited in the last 7 days. Staff confirm, and the system queues one invitation each.
- **A timer job** in `SystemService::runTimerJobs()` sends queued invitations, up to `iSignInInvitesPerHour` in any 60 minutes.
  - The job runs from `src/cli/timerjobs.php`, or from the page-footer fallback.
  - At send time it checks §4 again, skipping anyone who no longer qualifies.
  - It creates a `signInSetup` token and sends `SignInInvitationEmail`, with the church name, the logo, the link, and "If you weren't expecting this, you can ignore it".
- **Logging:** sends are logged like any other email (`email_log_eml`), so they appear in the person's email history.
- **Progress:** the tab shows queued, sent, skipped and failed. Staff can cancel what is still queued.
- **Without SMTP**, the button is disabled.

### 7.4 Admin → Users

- **The list shows staff accounts only**, retitled "Staff accounts".
- **The "Add User" button** (`system.php:70`) reads **"Add New Admin"**. It opens the person picker and the permission editor.
- **The editor no longer starts with every permission off** (`system.php:1072-1077`). The administrator picks Administrator or Custom. Saving Custom with nothing ticked asks for confirmation that this is read-only staff access, which can read every person and family (#9003).
- **"Member Portal only" stays in the editor**, to turn a staff account back into a member account.

### 7.5 Statistics

`PortalStatsService` adds these counts:
- eligible;
- set up;
- not set up;
- signed in during the last 30 days;
- disabled;
- needs attention.

"Member Portal accounts" are still `usr_EditSelf = 1 AND usr_Admin = 0` (`src/ChurchCRM/Portal/PortalStatsService.php:178-183`).

## 8. Email changes

Once an email is a sign-in name, changing it changes how that person signs in. These rules apply to a person who has an account:

- **The member changes it in the portal:** they enter their current password, and the old address gets a notice.
- **Staff change it:** only an administrator may change the email of a person with staff permissions. Staff with Edit Records may change a member's email.
- **Every change** sends `SignInEmailChangedEmail` to the old address. It names who made the change and when, without giving the new address.
- **A username equal to the old email is cleared**, so the person signs in with the new email. Other usernames, such as `admin`, stay.

Every path that writes `per_Email` calls one service method that enforces these rules:
- `src/PersonEditor.php:412`;
- the CSV import (`src/admin/routes/api/import.php:813`);
- the portal profile (`PortalSelfService.php:45-56`);
- the person API.

Public registration and kiosk walk-ins create new people awaiting review, so they can't affect an account.

## 9. Teenagers and the portal (D17)

- **Minimum age.** With the default of 13, teenagers can sign in, typically to see their volunteering schedule. A church can raise the age.
- **Adults.** `PortalSelfService::isAdultOf()` (`PortalSelfService.php:207-216`) adds an age test: head or spouse role (`Family::getAdults()`, `Family.php:479-482`) **and** not under 18. A person with no birth year keeps today's role-only rule.
- **What a member under 18 can't do in the portal:**
  - edit the family's details;
  - add a family member;
  - confirm the family's details.
- **What a member under 18 can do:** see their own family's names, emails, phones and address, as any family member can today, and edit their own profile.
- **Two-factor** is optional for members (D13).

## 10. Data model and settings

| Change | Detail |
|---|---|
| `person_per.per_LoginDisabled` | `tinyint(1) unsigned NOT NULL DEFAULT 0` |
| `tokens` | Hashed storage; ORM type of `valid_until_date` becomes `TIMESTAMP`; new type `signInSetup`; outstanding `password` tokens are hashed by the migration |
| New table `signin_invite_sinv` | `sinv_ID` (key), `sinv_per_ID` (foreign key to `person_per`, deleted with the person), `sinv_QueuedBy`, `sinv_QueuedAt`, `sinv_SentAt`, `sinv_Status` (queued, sent, skipped, failed, cancelled), `sinv_Error`; indexes on status and queue time, and on the person |
| `user_usr` | No change. Member accounts use NULL `usr_UserName` and `usr_apiKey` (both unique keys allow NULL repeats). |
| Settings (`buildConfigs()` only) | `bMemberSignIn` (0), `aMemberSignInClassifications` (`[]`), `iMemberSignInMinAge` (13), `iSignInInvitesPerHour` (100) |
| `bRequire2FA` | New label: "Require two-factor for staff accounts" |

The schema changes go in `Install.sql`, `orm/schema.xml`, one upgrade script, and `upgrade.json`.

**Code that must accept a NULL username:**
- the 36 `getUserName()` calls in 17 files, mostly log lines and admin lists;
- the account-email login link (`src/ChurchCRM/Emails/users/BaseUserEmail.php:46`).

The login link will carry the email, URL-encoded.

## 11. Security notes

- **Confinement doesn't change.** A member account is `isEditSelfExclusive()`. It is kept to `/portal`, `/api/portal`, the account-security pages and its own volunteering by `AuthMiddleware::isLimitedAccessAllowedPath()` (`src/ChurchCRM/Slim/Middleware/AuthMiddleware.php:235-272`) and `src/Include/PageInit.php:26-27`. Nothing in this proposal widens that list. The risk the proposal removes is an account with every permission off, which can read every person and family; D12 keeps automatic accounts away from it.
- **Throttling and lockout (#10197):**
  - throttling per IP and per identifier on login, the 2FA step, and reset/setup requests;
  - temporary lockout with backoff instead of the permanent lockout at `iMaxFailedLogins` (`User.php:714-717`);
  - identical responses and timing for unknown, locked and wrong-password cases.
- **Tokens:** hashed, single-use, used up atomically, revoked by a newer token, and never used up by a GET.
- **Sign-in emails** go only to the person's own address (§6.1). Email changes follow §8.
- **API keys:** member accounts get none. The portal already refuses API keys (`PortalAccessMiddleware.php:32-37`, `PortalApiMiddleware.php:31-36`).
- **Last administrator:** Disable login, and deactivating the family of the last administrator who can sign in, are both refused. This extends `UserService::isLastSignInCapableAdmin()` (`UserService.php:98-115`).
- **Mail reputation:** invitations stay within an hourly limit, and each says how to ignore it.
- **Privacy:** under-13s are excluded by default. Teenagers see only their own family (§9).

## 12. Alternatives considered

- **Moving the account columns onto `person_per`.** Rejected:
  - it adds 45 columns to every person row;
  - it touches the authentication interface, 359 `getCurrentUser()` calls, Propel models, a large migration and 48 Cypress files;
  - plugins that read users would need changes;
  - a member would see no difference.
- **Creating accounts for everyone at upgrade.** Rejected: thousands of empty rows, API keys for each, and it does nothing that creating the row at the first password doesn't.
- **"Which person are you?" for a shared email.** Rejected for v1:
  - each person still needs their own password;
  - it shows household names to whoever controls the inbox;
  - giving history per person (#10033, D3) needs separate identities anyway.
- **Signing in with the family email for the head of household.** Rejected: one shared inbox would control the account.
- **Sign-in by emailed link with no password.** Deferred. It could reuse the same token table later.

## 13. Proposed breakdown (not filed)

| ID | Issue | Needs |
|---|---|---|
| SI-0a | #10197: throttling, temporary lockout, uniform responses (already filed) | — |
| SI-0b | Reset link opens a Set Password form; no emailed passwords; hashed, single-use tokens | — |
| SI-1 | `per_LoginDisabled` and the `disabled` block reason; card on the person's page with Disable/Allow | SI-0b |
| SI-2 | `family_inactive` and `awaiting_review` block reasons; last-administrator guard on family deactivation | — |
| SI-3 | Sign-in settings, the member rule, the Sign-in tab (switch, classifications, minimum age) | SI-2 |
| SI-4 | Sign-in by email (§5.1), Needs attention list | SI-3 |
| SI-5 | Setup at first password: member account created by the Set Password form; one request flow | SI-0a, SI-0b, SI-4 |
| SI-6 | Send setup email / Copy setup link on the person's page | SI-5 |
| SI-7 | Invitations: table, timer job, button and progress | SI-6 |
| SI-8 | Card permissions editor; Admin → Users as "Staff accounts" with "Add New Admin"; no preset in the editor | SI-1 |
| SI-9 | Delete rule (D16) | SI-8 |
| SI-10 | 2FA mandate for staff only; no API keys for members; login API refuses member accounts; sessions end on password change | SI-5 |
| SI-11 | Email-change rules, notice to the old address, username clearing | SI-4 |
| SI-12 | Teenagers in the portal (D17) | SI-3 |
| SI-13 | Statistics (§7.5) | SI-5 |
| SI-14 | Visible renames (D18) and documentation | SI-8 |

Each issue that changes what users see opens its own docs tracking issue.

## 14. Visible text renamed (D18)

| Where | Today | Proposed |
|---|---|---|
| `src/admin/views/user-editor.php:85` | "Self-service only" / "Can only review and verify their own family. No other access." | "Member Portal only" / "Can sign in to the Member Portal only: their own profile and family, the calendar and volunteering." |
| `src/admin/views/users.php:122,152` | "Self-service", "Self-service only" | "Member Portal", "Member Portal only" |
| `src/v2/templates/user/user.php:417,420` | "Self-service only", description | "Member Portal only", the new description |
| `src/admin/views/member-portal.php:262,272` | "…self-service accounts only…", "Self-service accounts" | "…Member Portal accounts only…", "Member Portal accounts" |
| `src/ministries/views/ministry-view.php:263` | "…whose login is self-service only…" | "…whose login is Member Portal only…" |

- **Code identifiers stay as they are:** `usr_EditSelf`, `PortalSelfService`, `accessMode=self`, and the Cypress `selfedit` keys.
- **Translations:** these `msgid`s change, so the 43 translations are refreshed by the next locale run.

## 15. Tests (Cypress)

- **Sign-in and blocks:**
  - sign-in by email and by username;
  - a shared email is refused and listed;
  - each block reason refuses sign-in and ends an open session;
  - the member rule only applies while the switch is on.
- **Setup and reset:**
  - setup from the login link creates a member account that lands in the portal;
  - a GET on the link doesn't use it up;
  - a second token revokes the first;
  - unknown and known identifiers get the same answer.
- **Staff tools:**
  - Copy setup link works with email disabled;
  - invitations stay within the hourly limit and skip people who no longer qualify;
  - Disable login: who may toggle it, and the last-administrator guard.
- **Permissions and email:**
  - email-change rules and the notice to the old address;
  - teenagers can't edit or confirm family details;
  - a member account gets no API key, and the login API refuses it;
  - the 2FA mandate leaves members alone.
- **Existing specs:** the self-service specs (`private.selfedit.*`, `private.user-current.2fa-editself.*`) keep passing.

Related: #8977, #10246, #9865, #10193, #10197, #9696, #9003, #8617, #10355, #10033.
