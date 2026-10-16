# Member Portal sign-in: email and passkeys, portal permissions, and email that belongs to people

**Status:** revised proposal, for George's approval before any work starts. It replaces the password-based design first posted on this PR. Nothing is built, and no child issues are filed.

**Parent:** #10463 · **Related:** #10511 (authorization RFC), #10392 and #9843 (Login as User), #10197 (throttling), #8977 (Member Portal)

## Summary

- **Members sign in with their own email address.** ChurchCRM emails a code and a link. Members have no user account and no password. The web root opens the member sign-in page, `/portal/login`.
- **Passkeys come in the same release.** After the first email sign-in, a member can choose to add a passkey or to stay signed in on their own device. Email is then only for the first time and for getting back in. Nothing is remembered on a device unless the member asks, because it could be a shared or public computer. Google and Microsoft sign-in come later.
- **Staff sign in at `/admin`**, with their username, password and two-factor, as today. `/admin` takes over from `/session/begin`.
- **One shared step.** Every way of signing in ends at the same step: "start a portal session for this verified person". That step checks who may sign in, records the sign-in, and is the only code that creates a member session.
- **One portal permissions model.** One check decides what each member sees and can do in the portal. Core pages, Volunteer and plugins all use it. Team leaders keep running their teams from the portal.
- **Email belongs to people.** The family email moves to the head of household or spouse. They can sign in with it only after staff confirm it is theirs, unless they are the family's only living, active member. The printed directory shows each person's email only.
- **Disable sign-in.** An administrator can stop any person signing in.
- **Login as User** lets an administrator see a member's portal, from the person's Actions menu, read-only by default and on the record. It can't open a staff account.
- **Release-day cutover.** The existing self-service logins stop on the day this ships.

## How we got here

| Date | Where | What was said |
|---|---|---|
| 10-07 | #9843, #10392 | Login as User was rolled back from 7.8.0. George said it returns off by default, read-only by default and with a record of every action. |
| 10-09 | #10463, this PR | First design: member accounts with passwords. |
| 10-09 | George's review on this PR | Email sign-in, with no member accounts or passwords. SMTP is required. The link lasts 2 hours, and the session ends after 30 idle minutes. Failures go to the office. The admin page gets lists and an audit. |
| 10-10 | Carl's comment | Six points: email on every visit keeps people out; portal permissions for team leaders; passkeys and OAuth, with one shared session step; Disable login; identity without a user row; two sign-in pages. |
| 10-10 | George's reply | "Good with all." The authorization model needs redoing, ideally in the same release, but much can be done without it. George will make sure the APIs exist for a mobile app. |
| 10-11 | #10511 | The authorization RFC: typed principals; portal-only is never inferred from permission flags; personal email unique when supplied (P4); contact email is not a sign-in identity by default (R3); whole grants (T1); revocation by the next request (S5). |
| 10-12 | #10511, Carl's review | N3: families lose the family email, which moves to an adult. |

This document puts those into one design and one delivery plan.

## 1. Decisions

**Agreed on this PR**

| ID | Decision | From |
|---|---|---|
| A1 | Members have no user account and no password. Staff keep a username, password and two-factor sign-in. | George, 10-09 |
| A2 | A member signs in with a code and link sent to their personal email. A family email never matches. The address must belong to exactly one person who may sign in. If two people share it, neither can use it until an administrator fixes the records. | George, 10-09 |
| A3 | SMTP is required. There is no copy-a-link fallback. | George, 10-09 |
| A4 | The email is valid for a set time (default 2 hours). A new request replaces the old one. The link opens a confirm page, so mail scanners don't use it up. The email is spent when the person confirms or types the code. | George, 10-09 |
| A5 | A member session ends after a set idle time (default 30 minutes). | George, 10-09 |
| A6 | A failed attempt sends no email to the person, and the reason goes only into the audit. An office list on the settings page is emailed, with repeats held back. | George, 10-09 |
| A7 | The Member Portal admin page lists who may sign in, recent sign-ins, people who have never signed in, emails that need attention and recent family confirmations, plus a filterable audit. | George, 10-09 |
| A8 | Existing self-service logins stop on the release day, with no grace period. | George, 10-09 |
| A9 | Email is the way in the first time and the way to recover, not something a member goes through on every visit. | Carl point 1, agreed 10-10 |
| A10 | Portal features can be granted per person, so team leaders and coordinators don't need staff accounts. | Carl point 2, agreed 10-10 |
| A11 | Passkeys are in scope (first release). Google and Microsoft sign-in are in scope for a later release. A mobile app is kept in view but isn't in scope. | Carl point 3, agreed 10-10 |
| A12 | Every way of signing in ends at one shared step. | Carl point 3, agreed 10-10 |
| A13 | Each person has a "Disable sign-in" switch. It is off by default and only an administrator can change it. Turning it on ends every session, removes passkeys and remembered devices, and is audited. | Carl point 4, agreed 10-10 |
| A14 | Members and staff have separate sign-in pages. A member session never opens the staff side. | Carl point 6, agreed 10-10 |

**Made in this design**

| ID | Decision | Where |
|---|---|---|
| D1 | A member session is a portal principal for a person. Members have no `user_usr` row. Their sign-in data lives in new person-keyed tables. | §3 |
| D2 | This work starts now, built to the shape of #10511's typed principal and `decide()`, without waiting for its F0. It gets a milestone when it is scheduled. | §15, §16 |
| D3 | The sign-in page shows the same answer whether or not the address can sign in. | §6.1 |
| D4 | Passkeys are in the first release. Staying signed in is the member's choice, device by device. The box is never ticked for them, and a device stays signed in for 30 days by default. | §6.2, §6.3 |
| D5 | **Who may sign in:** family roles; classifications, which is where guests are decided; and a minimum age of 13. Deceased and inactive people can't sign in, and neither can people whose family is deactivated, who are awaiting review, or who have Disable sign-in on. In the portal, an adult is a head of household or spouse who is 18 or older. | §4 |
| D6 | The personal email is the sign-in identity. §9 controls who may change it, and when a new address may be used to sign in. | §9 |
| D7 | The web root goes to `/portal/login`. `/admin` is the staff sign-in page, and `/session/begin` redirects to it. | §7 |
| D8 | One portal permissions model serves core, Volunteer and plugins. | §8 |
| D9 | **Family email:** it moves to the head of household, then the spouse. If neither can take it, it is kept as a note in the family's timeline. A moved address is used to sign in only after staff with Edit Records or an administrator confirm it is that person's own. In a one-person family it is accepted as the address on record (§9). | §10 |
| D10 | Login as User returns inside this work, in the person's Actions menu. #10392 closes in favour of it. | §6.5 |
| D11 | **Release day:** each self-service account's portal data is copied, then the account is deleted. Member sign-in stays off until an administrator turns it on. | §12 |
| D12 | Passkeys add one WebAuthn library. | §14 |

## 2. Today

- **Who has portal access.** Only people staff have made an account for, as "Self-service only" (`usr_EditSelf = 1`, not admin).
  - `User::isEditSelfExclusive()` (`src/ChurchCRM/model/ChurchCRM/User.php:151`) decides who is self-service.
  - `AuthMiddleware::isLimitedAccessAllowedPath()` (`src/ChurchCRM/Slim/Middleware/AuthMiddleware.php:235-272`) keeps them inside the portal.
- **How the portal knows the member.** It starts from the signed-in `User`.
  - `PortalAccessMiddleware` (`src/ChurchCRM/Portal/PortalAccessMiddleware.php:54`) and `PortalApiMiddleware` (`:38`) turn it into the member's person once per request and put that on the request as `portalActor`.
  - Every `PortalSelfService` method already takes that person, not a user.
  - Only 14 `getCurrentUser()` calls are left in the portal code, plus the portal volunteer pages' calls to `/api/ministries/me` and `VolunteerTeamLeaderMiddleware`.
- **Sign-in.**
  - A signed-out visitor to the web root goes to `/session/begin` (`src/index.php:55`, `AuthenticationManager.php:226`).
  - After sign-in, a self-service user lands on `/portal/` (`AuthenticationManager.php:299-307`).
  - There is no portal sign-in page.
  - `/admin/` is the admin-only Admin Dashboard (`src/admin/index.php:8-12`, `src/admin/routes/dashboard.php:47`). Other staff are sent to an access-denied page. The staff dashboard is `/v2/dashboard`.
  - Signed-out requests for any staff page are sent to `/session/begin`, with the page remembered so the person lands there after signing in (`AuthMiddleware.php:288-305`).
  - `/session/begin` is written into 15 places in `src/`. It is also used by the Cypress login command (`cypress/support/test-env.js:59`) and in about 80 spec files.
- **Sign-in blocks.** `User::getSignInBlockedReason()` (`User.php:680-698`) refuses deceased and inactive people. It is checked at sign-in, on every session request, for API keys and at password reset.
- **Portal settings** (Admin → Member Portal):
  - the theme;
  - whether the calendar and volunteering show;
  - whether members may edit their birthday;
  - the calendars members may subscribe to;
  - statistics, which count self-service accounts only (`PortalStatsService.php:56-191`).

  Nothing on the page says who may use the portal.
- **Portal permissions.**
  - Each portal page checks what it needs on its own: a setting, `PortalSelfService::isAdultOf()`, or the volunteer team scopes.
  - Team leadership is person-keyed: `team` rows in `volunteer_scope_vscp`. A self-service team leader already runs their team at `/portal/teams`: positions, the Volunteers grid, schedules, staffing, headcounts and substitute requests.
  - Coordinators need the staff flag `usr_ManageMyMinistries` (`User.php:357`, `:388-406`).
  - The portal nav (`PortalNav::build()`, `src/ChurchCRM/Portal/PortalNav.php:43`) has its own checks for each item. Templates use the globals `member.isTeamLeader` and `member.isStaff`. There is no Twig function for permissions.
  - **Plugins can't reach the portal.**
    - A plugin can add staff menu items, routes under `/plugins/…`, hooks, settings, and head and footer HTML (`src/ChurchCRM/Plugin/PluginInterface.php`, `plugin.json`).
    - There is no extension point for portal nav items, pages, templates or API.
    - Plugin routes under `/plugins/…` are closed to portal users (`AuthMiddleware.php:240-245`).
- **Family view in the portal.** Any family member sees every other member's email and cell phone (`PortalSelfService::getFamilyView`, `:132-178`), and any family member can confirm the family.
- **Family email** (`fam_Email`). It is used in many places:
  - **Editors and views:** the family editor and view, the family list, and the person editor (which colours the label red when the family has an email).
  - **Member Portal:** the family details form.
  - **Email:** the email composer (`EmailComposerService.php:75, 112`), group mailing (`PersonService.php:373`), new person and family notices, the email log's reverse lookup, and the MailChimp plugin.
  - **Verification and statements:**
    - `Family::getEmails()` (`Family.php:531`) returns the family email plus every member's email and work email. Family verification and the emailed tax statement use it.
    - Bulk confirmation (`ConfirmReportService.php:425-428`) skips families whose only address is the family email.
  - **Printed directory:**
    - the "Family Email" option (`bDirFamilyEmail`, on by default) prints it on family records (`PdfDirectory.php:381`);
    - single-person records fall back to it (`DirectoryReport.php:281`).
  - **Elsewhere:**
    - CSV export;
    - CSV import, which copies the first row's email into it (`admin/routes/api/import.php:755`);
    - the family API (`GET /family/{id}`) and `/families/email/without`;
    - family search;
    - the Gravatar photo lookup (`dto/Photo.php:297-307`);
    - the Duplicate Emails report (`people-persons.php:294`), which counts family, personal and work emails together.
  - **People without their own email:** some screens and messages reach them through the family email instead, such as the email composer, group mailing, volunteer and kiosk notifications, CSV export and the person page.
- **Calendar feed.** Each self-service account has a token in its feed URL (`usr_PortalCalendarToken`, `/api/public/portal-calendar/{token}/calendar.ics`). The feed doesn't check `getSignInBlockedReason()`.
- **Sign-in history.** Successes and failures go only to the rotating `auth.log` file, which is kept for 3 days (`LoggerUtils.php:205-243`).
- **Throttling.** Core has none. An account locks after 5 wrong passwords until an administrator unlocks it. #10197 is open.

## 3. Identity: the portal principal

A member session belongs to a **person**, not to a user account.

- **`PortalPrincipal`** is a small value object: the person id, how they signed in, when, and, for Login as User, the acting administrator. It lives in the PHP session.
  - `PortalAccessMiddleware` and `PortalApiMiddleware` resolve `portalActor` from the principal instead of from `getCurrentUser()`.
  - The remaining portal callers (§2) read the principal too.
- **The staff side doesn't change.** `User`, `AuthenticationManager` and every staff route keep working as they do now. A request with only a portal principal is not a signed-in user to them, so staff routes send it to `/admin`.
- **Sign-in data lives in person-keyed tables** (§13): sign-in state and Disable sign-in, sign-in requests, remembered devices, passkeys, portal grants and the audit. A row exists only for people who have signed in or have a setting.

## 4. Who may sign in

A person may start a portal session when **all** of these hold. The shared step (§5) checks them at sign-in, and the portal checks them again on every request.

**Blocks for everyone.** The staff sign-in checks these too.
- deceased or inactive (today's `getSignInBlockedReason()`);
- Disable sign-in is on (A13);
- the person's family is deactivated;
- the person is awaiting review (`per_NeedsReview`): public self-registration, kiosk walk-ins, and people a member added in the portal.

**The member rule** is set on a new Admin → Member Portal → Sign-in tab. These settings don't exist today. The rule doesn't apply to staff signing in at `/admin`.
- **Family roles:** one checkbox per family role (head of household, spouse, child, other relative…). Head of household and spouse are ticked at first.
- **Classifications:** one checkbox each (Member, Regular Attender, Guest…), with the number of active people in each.
  - This is where the church decides about guests.
  - Nothing is ticked at first.
  - Churches that imported historical records under a "non-attender" classification need this. Otherwise every imported person with an email could sign in.
- **Minimum age:** default 13, so teenagers who volunteer can see their schedules. When the birth date is unknown, only a head of household or spouse qualifies.

**The email rule** (A2):
- the personal email (`per_Email`) only. The work email never matches, and after §10 there is no family email;
- not while the address is unconfirmed (§9);
- an exact match after trimming and lower-casing;
- if two people who would otherwise qualify share the address, nobody signs in with it. Both people show up under "Email needs attention".

**Teenagers.** A member under 18 can sign in when the minimum age allows it. In the portal, an *adult* is a head of household or spouse who is 18 or older. Only adults see other family members' contact details, change family details, add family members or confirm the family (§8.4).

## 5. The shared step and the portal session

```php
// The only code that creates a member session.
PortalSessionStarter::start(
    int $personId,
    SignInMethod $method,   // EMAIL_LINK, EMAIL_CODE, DEVICE, PASSKEY, STAFF, ASSISTED; later GOOGLE, MICROSOFT, APP
    ?User $actor = null     // the administrator, for ASSISTED (Login as User) only
): PortalPrincipal;
```

It does the following, in this order:
1. **Checks.** It loads the person from the database and applies §4. `STAFF` skips the member rule. `ASSISTED` applies only the three checks listed in §6.5 and none of the others. If any check fails, it writes a "refused" audit row with the reason and throws. No session is started.
2. **Starts the session.** It regenerates the session id. It stores the principal and the person's current **session version**: a number on their sign-in row that goes up whenever all their sessions must end.
3. **Updates the sign-in row:** first and last sign-in time and the method. For an email method, it also records the address that was proven. `ASSISTED` changes none of these.
4. **Audits.** It writes a "signed in" audit row with the method, and the actor for `ASSISTED`.

**On every portal request**, `PortalSessionMiddleware`:
- reloads the person and their sign-in row;
- applies the checks for the session's sign-in method again: all of §4 for the member methods, §4 without the member rule for `STAFF`, and only the three checks in §6.5 for `ASSISTED`;
- ends the session when one of those checks fails, when the session version has changed, or when the idle time has passed;
- records last activity, except during Login as User.

So Disable sign-in, a change to the rule, a deactivated family or "sign out everywhere" all take effect on the person's next request (#10511 S5).

**A future mobile app** uses the same step. It gets a refresh token kept in the remembered-devices table (kind `app`), instead of a browser cookie. The app and its API are not part of this proposal.

## 6. Ways in

### 6.1 Email: code and link

1. On `/portal/login` the person types their email. The page always shows the same answer, with a box for the code: *"If this address can be used to sign in, we've emailed you a code. Nothing after a few minutes? Ask the church office."* The page never tells anyone whether an address belongs to a member (D3).
2. When the address matches exactly one person who may sign in, ChurchCRM:
   - cancels that person's earlier unused request;
   - stores a new request with a hashed link token (32 random bytes) and a hashed 6-digit code, both valid for the set time (default 2 hours);
   - sends one email with a fixed wording, containing the code and the link.
3. When it doesn't match, nothing is emailed to the person. The audit gets a "refused" row with the typed address and the reason in plain words. The office list gets a notice, and the same address doesn't trigger another notice within an hour.
4. **The link** opens a confirm page showing the church name and "Sign in as {first name}?". Opening the link changes nothing. Pressing **Sign in** spends the request, in one conditional `UPDATE`, and calls the shared step with `EMAIL_LINK`.
5. **The code** is typed on the same browser that asked for it. The request is tied to that browser by a cookie. Five wrong codes cancel the request, and the person has to ask again. A correct code spends the request and calls the shared step with `EMAIL_CODE`.
6. Both pages have a box, **This is my own device. Keep me signed in.** It is never ticked for the member. They tick it themselves (§6.2).

**Throttling** builds on #10197:
- per typed address: 5 requests an hour;
- per IP address: 20 requests an hour;
- per person: 5 emails a day.

Requests over a limit get the same answer screen and no email. They are audited as "throttled".

### 6.2 Remembered devices

- **Staying signed in is the member's choice, on each device.**
  - The box on the confirm page and the code page starts unticked every time.
  - Under it: *"Tick this only on your own phone or computer, never on a shared or public one."*
  - Without it, nothing is kept on the device. The session ends after the idle time (A5) or when the browser closes.
- When the member ticks the box, ChurchCRM sets a long-lived cookie (`HttpOnly`, `Secure`, `SameSite=Lax`, path `/portal`). The cookie carries a random token, and only its hash is stored.
- When the portal session has gone idle, the cookie starts a new one through the shared step, with method `DEVICE`. The rule is checked again. The token is replaced at each use, and it lasts the set number of days (default 30).
- Members see their devices and passkeys on a new **Sign-in and devices** portal page. Each device shows a browser and system label, when it was added and when it was last used. A member can sign out one device, or every device.
- Disable sign-in, "sign out everywhere", and an email change made by someone else (§9) remove all remembered devices.

### 6.3 Passkeys

- **A passkey is also the member's choice, on their own device.**
  - After an email sign-in on which the member ticked **This is my own device**, the portal offers it once: *"Sign in next time on this device with Face ID, a fingerprint or your device PIN."* The member can say yes or no.
  - It isn't offered when the box wasn't ticked, because the device may be shared or public.
- Members can also **Add a passkey** on the Sign-in and devices page.
  - This needs an email sign-in in the last 15 minutes. Otherwise a stolen cookie could create a lasting credential.
  - The page shows the same warning about shared and public computers.
- `/portal/login` then offers **Sign in with a passkey**, including the browser's passkey autofill. A passkey sign-in calls the shared step with `PASSKEY`.
- Passkeys need HTTPS and a stable host name. The page shows the option only when both hold, and the Sign-in tab says why when they don't.
- Adding a passkey sends a notice to the member's email.
- Disable sign-in removes every passkey. A passkey also stops working whenever the person may no longer sign in (§4).
- Passkeys are *not* removed when someone else changes the member's email. The real member keeps a way in.
- The church can turn passkeys off on the Sign-in tab. They are on by default.

### 6.4 Switching between staff and portal

- **Signed in as staff (at `/admin`):**
  - the staff menu's **Member Portal** link opens the staff member's own portal;
  - the portal header's **Admin Console** link goes straight back.
  - Neither link asks for anything. The portal sees a principal for their own person with method `STAFF`, which gets the §4 blocks but not the member rule.
- **Signed in at `/portal/login`** (email or passkey), by someone who also has a staff account:
  - the portal's **Admin Console** link goes to the `/admin` sign-in page;
  - only a username, password and two-factor sign-in there opens the staff side, and it then replaces the member session.
  - An email link or a passkey never opens the staff side.
- **The portal's Change Password and two-factor pages** show only in a staff session. Members have neither.

### 6.5 Login as User (folds in #10392)

Login as User lets an administrator see the Member Portal exactly as a member sees it, to help with a problem. It moves into this proposal, and #10392 closes in favour of its child issue.

- **Where it is:** a **Login as User** item in the **Actions** menu on the person's page (`src/people/views/person-view.php:436-474`). The Make User, Edit User and View User buttons in the page header (`src/people/routes/view.php:96-106`) stay where they are.
- **When it is grayed out**, with the reason on hover. These are the only checks Login as User applies, at start and on every request:
  - the person is deceased;
  - the person's Disable sign-in is on;
  - the person has a user record (a staff account). Login as User never opens a staff account.
- **Deliberately not checked.** The office uses Login as User to see what a person sees, including someone who can't sign in yet. So it still works when:
  - the person is inactive;
  - the person's family is deactivated;
  - the person is awaiting review;
  - the person falls outside the member rule (§4);
  - the person has no usable sign-in email;
  - member sign-in is off.

  The session is still read-only by default and fully recorded.
- **Who and when:** administrators only. The church turns it on in the setting *Allow Login as User*, which is off by default (#10392). While the setting is off, the item doesn't show and the routes answer 404.
- **How it works:**
  - It calls the shared step with `ASSISTED`, the member as the subject and the administrator as the actor.
  - The administrator's own staff session waits underneath it. While Login as User is active, staff pages are refused.
  - **Stop** returns to the administrator's staff session, on the person's page.
- **Read-only by default.** #9843 asked for this. Changes are refused unless the church also turns on *Allow changes during Login as User*.
- **A record of every action:** #10392's session and action tables (`user_masquerade_session_ums`, `user_masquerade_action_uma`), with the target changed from a user to a person.
  - Every write is recorded against both the administrator and the member, and so is every audit row (#10511 B1).
  - The history card shows on the person's page and on the administrator's user page.
- **Activity stats:** Login as User doesn't update the member's last activity or their sign-in statistics.
- **Dropped from #10392:** the rule about the target's two-factor sign-in. Members have no two-factor, and staff accounts can't be targets.

### 6.6 Google and Microsoft (a later release)

- Sign-in uses the OpenID Connect authorization code flow, with PKCE, state and nonce checks. An administrator enters each provider's client id and secret.
- The provider's email is used **only when the provider says it's verified**. For Microsoft, that means the verified-domain claim, because a Microsoft account's `email` claim can be set to an address its owner doesn't control.
- A provider's claim doesn't prove that this person controls that exact mailbox. Microsoft's proves only the domain. So the first time a Google or Microsoft account is used, ChurchCRM emails a code to that address, as in §6.1, and links the account to the person only when the code is typed. Later sign-ins match the linked account (Google's `sub`, Microsoft's `tid` and `oid`), not the email.
- The verified email goes through the same email rule as §4, and then the shared step with `GOOGLE` or `MICROSOFT`.
- The detailed design is left to its own issue. It needs a second library.

## 7. Sign-in pages

| Page | Who | Asks for |
|---|---|---|
| `/portal/login` | members | email, or a passkey |
| `/admin` | staff | username, password, then two-factor |

- **Web root.**
  - Signed out, it goes to `/portal/login`.
  - Signed in, it goes to the staff dashboard for a staff session and to the portal for a member session.
- **`/portal/login`** uses the church's portal theme and has a small **Staff sign-in** link to `/admin`. While member sign-in is off, it says *"Member sign-in isn't available yet. Please contact the church office."*, with the same Staff sign-in link.
- **`/admin`**
  - Signed out, it shows today's staff sign-in: username and password, then the two-factor step, with "Forgot password?".
  - Every staff page a signed-out visitor asks for redirects to `/admin`, which remembers the page and returns there after sign-in. Today that redirect goes to `/session/begin`.
  - Signed in:
    - an administrator gets the Admin Dashboard, as today;
    - other staff go to the staff dashboard (`/v2/dashboard`) instead of the access-denied page they get today;
    - a member session goes to the staff sign-in, as in §6.4.
  - Only the `/admin` root changes. Every other `/admin/…` page stays administrator-only.
- **`/session/begin`** redirects to `/admin`, so bookmarks, the docs and other links keep working.
  - The 15 places in `src/` that link to it, including the sign-in link in user emails (`BaseUserEmail.php:46`), change to `/admin`.
  - The Cypress login command moves to `/admin`. The spec files keep working through the redirect.
- The two-factor step, password reset and `/session/end` keep their paths in the first release. `/session/end` signs out either kind of session.

## 8. Portal permissions model

One model decides what every member sees and can do in the portal: core pages, Volunteer, and plugins. Core and plugins call the same check, and no portal page tests a role or a setting on its own.

### 8.1 Terms

- **Capability:** one thing a person may see or do in the portal, such as "edit my family's details" or "manage this team".
  - Each capability has a key, a label and a description for administrators, an owner (core, or the plugin's id) and a **scope type**.
  - Scope types are none, family, team, ministry, group, or one the owner declares.
- **Grant:** a person holds a capability on one scope, for example `volunteer.team.manage` on the Sunday Greeters team.
  - A grant is always a capability and a scope together. Two grants never combine into a third (#10511 T1).
- **Source:** where a grant comes from (§8.3). The admin pages always show it.
- **Feature switch:** a portal setting such as "Show the calendar" says whether a feature is on at all. It is not a permission. When a feature is off, its capabilities are denied for everyone (#10511 §4).

### 8.2 The check

```php
interface PortalAuthorization {
    /** May this person do this, on this thing? Unknown capability: no. */
    public function decide(PortalPrincipal $who, string $capability, ?PortalScope $on = null): Decision;

    /** Every scope this person holds the capability on, e.g. the teams they lead. */
    public function scopesFor(PortalPrincipal $who, string $capability): array;
}
```

- `Decision` holds yes or no and a reason. The reason is for logs and the admin preview. It is never shown to the member.
- An unknown capability is a no, as is one whose owner is turned off or whose feature switch is off.
- **Pages and API:** `RequirePortalCapability` route middleware takes the capability and how to read the scope from the route (for example `{teamId}`). It answers 404 when the scope exists but isn't the person's, so ids can't be probed.
- **Templates:** a new Twig function, `portal_can('volunteer.team.manage', team)`, shows or hides a section or button. It replaces the `member.isTeamLeader` and `member.isStaff` globals.
- **Navigation:** each portal nav item names the capability it needs. It shows when `scopesFor()` returns anything. Today's own checks in `PortalNav::build()` move to this.
- **Lists:** "My Teams" and similar lists come from `scopesFor()`, so a page lists exactly what the person may open.

### 8.3 Where grants come from

1. **Everyone signed in.** The baseline every member has: their own profile, their family, the calendar and volunteering (each when its feature is on).
2. **Rules.** Code that works out grants from church data, live, on each request. Nobody assigns these, and they end when the data changes.
   - Core rules:
     - *adult of the family*: head of household or spouse, 18 or older (§4);
     - *team leader*: from the volunteer `team` scope rows.
   - Plugins can add rules (§8.5).
3. **Given to a person** by an administrator, on the person's page: capability, scope, an optional end date and a reason. Every grant and removal is audited.

Not in this proposal: an administrator giving a capability to a whole group or family role at once. A rule can already derive grants from group roles. If churches need it, it can be added later as a fourth source.

### 8.4 First catalog

| Key | Scope | Source | What it allows |
|---|---|---|---|
| `portal.profile.edit` | — | everyone | edit my own details |
| `portal.family.view` | family | everyone | see my family: names and photos |
| `portal.family.contacts.view` | family | adult rule | see family members' email and phone |
| `portal.family.edit` | family | adult rule | change family details, add a family member |
| `portal.family.confirm` | family | adult rule | confirm the family's details |
| `portal.calendar.view` | — | everyone, when the calendar is on | church calendars and my subscription |
| `portal.volunteer.use` | — | everyone, when volunteering is on | my schedule, sign-ups, substitute requests |
| `volunteer.team.manage` | team | team-leader rule | positions, Volunteers grid, schedules, staffing, headcounts |
| `volunteer.team.substitutes` | team | team-leader rule | approve or decline substitute requests |

### 8.5 Plugins

Today a plugin has no way into the portal (§2). This proposal adds one, built on the existing plugin system: `plugin.json`, `PluginInterface` and `AbstractPlugin`. A plugin that doesn't use it is unaffected.

- **Capabilities.** A plugin declares them in a new `portal.capabilities` block in `plugin.json`: key, label, description and scope type.
  - Keys take the form `plugin.<id>.<name>`, as in #10511 §7.
  - A capability that isn't declared can't be checked. Any check for it says no.
- **Rules.** A new optional method, `getPortalRules(): array`, returns the plugin's rules. For example: "leaders of a group get `plugin.smallgroups.roster.view` on that group".
- **Pages and API.** A new `portalRoutesFile` in the manifest mounts the plugin's routes:
  - pages under `/portal/plugins/<id>/…`;
  - API under `/api/portal/plugins/<id>/…`.

  Both sit behind the portal's session middleware. Each route declares its capability with `RequirePortalCapability`. Pages render with the church's portal theme, from the plugin's own `portal/templates` folder.
- **Navigation.** A new optional method, `getPortalNavItems(): array`, returns items shaped `{label, url, icon, capability}`. Each shows only when the person holds the capability.
- **Turning a plugin off.** Its capabilities, rules, pages and nav items disappear, so every check for them says no. Grants given to people stay stored. They work again if the plugin is turned back on, and are deleted when it is uninstalled.
- **Trust.** A plugin is trusted code in the same process (#10511 §7). The model gives plugins one consistent way to decide. It doesn't sandbox them.
- **Defaults.** `AbstractPlugin` returns empty lists for the new methods, so existing plugins don't change. The plugin README and the plugin skeleton get a portal example.

### 8.6 What administrators see

- **Admin → Member Portal → Permissions:** every capability, core and plugin, with its description, its sources and how many people hold it. Opening one lists the holders and where each grant comes from.
- **The person's page, "Portal sign-in" card:** the person's portal permissions with their source (for example "Everyone", "Adult of the Smith family", "Leader of Sunday Greeters", or "Given by Pat until 31 Dec"; dates follow the church's date format, time zone and language settings). Administrators add and remove given grants there.

### 8.7 Other ideas the model supports (not scheduled)

Neither of these is planned or scheduled. They are listed to show the kind of portal feature the model is built for:
- **A teacher starts a kiosk session from the portal.** A Sunday School teacher opens check-in for their own class from their phone or the classroom tablet, without an administrator setting up the kiosk.
  - This would be one capability, scoped to the class.
  - It would come from a rule (teacher of that class) or be given to a person.
  - It would also follow #10511's kiosk device rules (#10518).
- **An attendance counter enters event attendance from the portal.** An usher or greeter records the attendance for an event they are assigned to. This would be one capability, scoped to the event, given to a person.

## 9. The sign-in email is a credential

Under A2, whoever controls a person's personal email can sign in as that person. An email sign-in proves that whoever signs in controls that exact mailbox, because the code and link go only to that address (§6.1). It doesn't prove that the mailbox is that person's. So changes to that email follow these rules. The first time a person signs in by email, ChurchCRM records the address as proven. "Has signed in" below means that has happened.

| Who changes it | Rule |
|---|---|
| The member, in the portal | The new address gets a confirmation link, and the change happens when the link is used. The old address gets a notice. Today the profile form saves it at once. |
| A family adult, in the portal | Can't change another member's personal email, as today. |
| Staff with Edit Records | Allowed, as contact data. If the person has signed in, all their sessions and remembered devices end, the old address gets a notice, and the new address is unconfirmed (below). Their passkeys stay. |
| Anyone, for a person with given portal grants (§8.3) or a staff account | Administrators only. |
| Imports and the API | Follow the staff rule. |

**Addresses on record before the first sign-in.** Until a person has signed in, the address the church has on record for them is their sign-in identity (A2). Receiving the code at that address is the accepted proof of identity: it shows that whoever signs in controls the mailbox the church recorded for that person. ChurchCRM checks nothing more. Two kinds of address work this way and can be used to sign in at once:
- an address that staff, an import, the API or a registration form give a person before their first sign-in;
- an address moved from the family email to the only living, active member of a one-person family (§10.1).

The rest of the design still applies to these addresses:
- the address must belong to exactly one person who may sign in (§4);
- every change is audited;
- the first sign-in is audited with the address it used.

**Unconfirmed addresses.** An unconfirmed address is the person's email for mail at once, but it can't be used to sign in until it is confirmed. Two kinds are unconfirmed:
- a new address that staff, an import or the API give a person who has signed in;
- an address moved from the family email to a person in a family of two or more (§10.1).

Saving the same address again doesn't confirm it. An address is confirmed when:
- the member signs in with a passkey and confirms it on the Sign-in and devices page, which sends a confirmation link to the new address, as when they change it themselves;
- the member uses the link in the notice sent to their old, proven address;
- someone who has checked who the person is, in person or by phone, confirms it on the person's page or the Sign-in tab:
  - for an address moved from the family email: staff with Edit Records, or an administrator;
  - for a replacement address after the first sign-in: an administrator. Staff with Edit Records can set that address, so they can't also vouch for it;
  - for a person with given portal grants or a staff account: an administrator, as for any change to their email.

An address moved from the family email has only the last way, because everyone who reads the family's mailbox would get the code and the person has no other way in yet. Until an address is confirmed, the person is listed under "Email needs attention". After it is confirmed, the first email sign-in still proves the mailbox.

Every change and confirmation is audited with the old and new address, who made it, and how.

This is how #10511 R3 applies to portal principals: a notice to the old address, an audit row, limits on who can make a change, and no sign-in with a replacement address until the person or an administrator confirms it.

## 10. Family email moves to the people

Families lose their own email address. Email belongs to people, and that is also how members sign in (A2). This settles #10511 N3.

### 10.1 The move (upgrade)

For each family whose email isn't empty (compared trimmed and in lower case):

1. **Someone already has it.** If any person has that address as their personal email, nothing is copied.
   - If that person is in the family, nothing more happens.
   - If that person is in another family, the family gets a timeline note (step 4).
2. **Head of household.** The address goes to the head of household, when the head has no personal email.
3. **Spouse.** Otherwise it goes to the spouse, when the spouse has no personal email.
   - Only living, active people count in steps 2 and 3.
   - When a family has more than one head or spouse, the oldest goes first.
4. **Nobody can take it.** Otherwise the family gets a note in its timeline: *"The family email {address} was not moved to a person: {reason}."* The reasons are:
   - the head of household and spouse both have their own email;
   - the family has no living, active head of household or spouse;
   - another person ({name}) already uses this address.
5. **Then the family email is cleared.**

- **A moved address doesn't sign in yet.** It is unconfirmed (§9) until staff with Edit Records or an administrator confirm that it is that person's own, because everyone who reads the family's mailbox would get the sign-in code. Mail goes to it at once (§10.2).
  - **Except in a one-person family.** When the person who receives it is the family's only living, active member, it is an address on record before the first sign-in (§9) and can be used to sign in at once. Receiving the code is the accepted proof of identity.
- A one-time **Family emails not moved** list on the Member Portal Sign-in tab links to each family with a note, with counts: moved to a head, moved to a spouse, already held, and noted.
- The notes are ordinary family notes, written with `Family::createTimeLineNote()` (`Family.php:551`). Staff who can see the family's timeline can read them.
- The move is plain SQL in the 7.9.0 upgrade script (`src/mysql/upgrade/7.9.0.sql`), the same file the other 7.9.0 work adds to. It uses no window functions, so it runs on every database ChurchCRM supports.
- **Some people lose email:** those who were reached only through the family email, and who aren't the head or spouse who receives it. Most are children. The Sign-in tab's "Email needs attention" list shows them.
- The `fam_Email` column stays empty for one release, then a later upgrade drops it. Any family email added during that release by an older import or API client can be moved by the same rule before the drop.

### 10.2 After the move

- **Mail meant for one family address** goes to the head of household's personal email: the email composer's family recipient and new-family notices. When the head has none, it goes to the spouse's. When neither has one, nothing is sent and the screen says why.
- **`Family::getEmails()`**, used by family verification and the emailed tax statement, keeps returning every member's email. Only the family email is dropped.
- **Bulk confirmation** stops skipping families. Every family with an adult who has an email is included.
- **A person without their own email has no email.** Nothing reads the family email in a person's place any more. Group mailing and notifications skip them, and the composer says so.
- **The family editor and family page** lose the Email field. The family page shows each adult's email instead.
- **Member Portal:** the family details form loses its Email field. Each member keeps their own email on their profile (§9).
- **Public registration:** the API's family `Email` field (`public-register.php:120`) is saved on the person who registered.
- **CSV import:** an email that today is copied into the family email goes through the rule in §10.1 instead. **CSV export:** each person's own email only.
- **REST API:** the family endpoints stop reading and writing `email`. The release notes list this as an API change.
- **Printed member directory:**
  - the "Family Email" option (`bDirFamilyEmail`) and its line on family records are removed;
  - single-person records stop falling back to the family email;
  - each person's email still prints as it does today, under "Personal Email".
- **Family search, the Gravatar photo lookup and the Duplicate Emails report** stop using the family email. After the move, the Duplicate Emails report shows exactly the shared personal emails that block sign-in.
- **Everything else** that reads the family email (§2) follows the same head-then-spouse rule, or drops the family email where it only displayed it.

## 11. Admin pages

### 11.1 Admin → Member Portal → Sign-in

- **Member sign-in**, on or off. It can only be turned on while SMTP works, and it is off after the upgrade and on a new install.
- **Who may sign in:** family roles, classifications with counts (guests included) and the minimum age.
- **Link and code lifetime:** default 2 hours.
- **Idle time:** default 30 minutes.
- **Keep me signed in:** how many days a device stays signed in after the member ticks **This is my own device** (default 30). Setting it to 0 removes the box.
- **Passkeys:** on or off, with the HTTPS and host-name check.
- **Office list:** people to email about failed attempts. Only people with an email can be added.
- **Login as User:** the two switches from §6.5.

### 11.2 Lists and audit (A7)

- **Lists:**
  - who may sign in now;
  - who signed in during the last 30 days;
  - who may sign in but never has;
  - email needs attention: no personal email, a personal email shared with someone else, or an unconfirmed one (§9), which staff can confirm from the list where §9 lets them;
  - families confirmed in the last 30 days;
  - family emails not moved (one time, after the upgrade).
- **Audit**, filterable by person and date. Each row has a time, the person, the event, the method and, for staff actions, who did it.
  - Events: email requested, signed in (with the method), refused (with the reason), throttled, session ended (with the reason), device added or removed, passkey added or removed, email changed or confirmed, sign-in disabled or enabled, grant added or removed, Login as User started and stopped, family confirmed.
  - "Family confirmed" records which member confirmed. Today's confirmation note doesn't.
  - The audit never stores a code, link, token, password or passkey secret.
  - It replaces the 3-day `auth.log` file as the record for member sign-in. The file keeps logging, as today.
- The **Statistics** tab reads the new tables instead of the self-service accounts.

### 11.3 The person's page

- **"Portal sign-in" card:**
  - everyone with Edit Records sees whether the person may sign in (or why not), their last sign-in, and how many devices and passkeys they have. They can also Confirm an email moved from the family email (§9);
  - administrators also get Disable sign-in (with a reason), Confirm for any unconfirmed email (§9), Sign out everywhere, the list of devices and passkeys with a remove button on each, and the portal permissions (§8.6).
- **Actions menu:** Login as User (§6.5). The Make User, Edit User and View User buttons in the page header stay as they are.

## 12. Release-day cutover

- **Upgrade script.** For every self-service account (`usr_EditSelf = 1` and not admin, the same test as `isEditSelfExclusive()`), the script:
  - copies the portal data to the person's sign-in row: the calendar feed token and selection (so calendar subscriptions keep working) and the last portal activity;
  - then deletes the account. The release notes say this, and the usual backup before an upgrade is the way back.
- **The calendar feed** looks up the person by token in the new table. It stops working when the person may no longer sign in (§4), which today's feed never checks.
- **Member sign-in stays off** until an administrator turns it on. If any self-service accounts were deleted, the dashboard shows "N people used to sign in to the Member Portal. Turn on member sign-in." The notice stays until member sign-in is on or an administrator dismisses it.
- **The family email move** (§10) runs in the same upgrade, before the sign-in tables are filled.
- **Admin → Users** lists staff accounts only. The user editor's "Self-service only" choice goes away, as does the code that created those accounts.
- **Read-only staff accounts** (every flag off) stay as they are. #10511 P5 makes read-only the starting point for new staff accounts.
- **The confinement code** (`isLimitedAccessAllowedPath()` and the `PageInit.php` redirect) stays for one more release as a safety net, then goes.
- **Wording.** "Self-service" becomes "Member Portal" in the visible text.

## 13. Data model and settings

New tables. All are keyed by `per_ID` with `ON DELETE CASCADE`, except the capability-level ones.

| Table | Holds |
|---|---|
| `person_signin_psi` | one row per person who has signed in or has a setting: Disable sign-in (with who, when and why), session version, proven email and when, an unconfirmed email and why (§9), first and last sign-in, last activity, calendar feed token and selection (moved from `user_usr`) |
| `person_signin_request_psr` | pending email requests: hashed link token, hashed code, browser binding hash, expiry, attempts, used at |
| `person_device_pdv` | remembered devices, and later app tokens: hashed token, kind (`browser` or `app`), label, created, last used, expiry, revoked |
| `person_passkey_ppk` | passkeys: credential id, public key, sign counter, label, created, last used |
| `portal_grant_pgr` | given portal grants: person, capability key, scope type, scope id, ends, reason, granted by and when |
| `portal_signin_audit_psa` | the audit in §11.2 |
| from #10392 | `user_masquerade_session_ums` and `user_masquerade_action_uma`, with the target changed from a user to a person |

- **Settings** (in `config_cfg`):
  - `bMemberSignIn`;
  - the member rule settings;
  - `iSignInLinkMinutes` (120), `iPortalIdleMinutes` (30), `iRememberDeviceDays` (30);
  - `bPortalPasskeys` (on);
  - the office list;
  - `bAllowLoginAsUser` (off) and `bLoginAsUserCanWrite` (off).
- **Capabilities** live in code (core, and plugins at boot), not in a table. Only given grants are stored.
- **Migration:** everything is plain SQL in `src/mysql/upgrade/7.9.0.sql`: the new tables and settings, copying the portal data, deleting the self-service accounts and moving the family emails (§10, §12). `Install.sql` and `cypress/data/seed.sql` get the same tables. `fam_Email` is dropped a release later.

## 14. Security notes

- **Tokens and codes:** hashed at rest with SHA-256, used once, with one live request per person. They are compared in constant time.
- **No side effects on GET:** opening a link only shows the confirm page.
- **Sessions:**
  - the id is regenerated at every sign-in;
  - the cookies are `HttpOnly`, `Secure` and `SameSite=Lax`;
  - every POST is checked for CSRF;
  - a portal principal is never accepted by a staff route.
- **Revocation by the next request:** the session version and the per-request check (§5).
- **No account lookup leak:** the one answer screen and the throttles (§6.1).
- **Email:** the sign-in email goes only to the person's own address. The office notice goes only to the office list.
- **Sign-in addresses:** an email sign-in proves control of the mailbox, not whose it is. A replacement address set by someone else, and an address moved from the family email in a family of two or more, sign in only once confirmed (§9). Before a person's first sign-in, receiving the code at the address on record is the accepted proof of identity (§9).
- **Audit:** it holds no secrets. Notes and other personal content never appear in it.
- **Remembered devices and passkeys:**
  - only when the member says the device is their own (§6.2, §6.3);
  - the relying-party id is the site host;
  - user verification is required;
  - adding one needs an email sign-in in the last 15 minutes.
- **Login as User:** administrators only, off by default, read-only by default, never into a staff account, every write recorded with both people.
- **Portal permissions:** deny by default; whole grants; scope checked on the loaded record; 404 for someone else's scope.
- **Library:** passkeys add one WebAuthn library to `src/composer.json`. It is chosen on the passkey issue (D12).
- **Protected households (#10524):** the portal's family pages show only the member's own household. Pages that list other people, such as a team's Volunteers grid, must apply #10524's record policy once it merges.

## 15. Fit with the authorization RFC (#10511)

This work does not wait for #10511's F0, as George said on 10-10. It is built so the RFC can take it over:

- **Typed principal.** `PortalPrincipal` is #10511's portal principal. The RFC's `Principal` can wrap it, carrying actor and subject for Login as User (B1).
- **Decision shape.** `PortalAuthorization::decide()` has the RFC's shape:
  - every grant is a capability plus a scope (T1);
  - rules wrap the existing domain scopes, as N1 asked;
  - plugin capabilities use the RFC's `plugin.<id>.*` keys.
  - When F0 lands, the RFC's service calls it for portal principals, or takes over its registry.
- **No portal user accounts.** Members aren't counted as "user accounts" in the RFC's V2-role readiness banner. The sign-in rule (§4) decides who may use the portal at all, and the permissions model (§8) decides everything after that.
- **P4 (unique personal email when supplied).** The email rule refuses shared addresses and lists them, without a database constraint. The family email move (§10) never creates a duplicate.
- **R3 (contact email versus sign-in identity).** §9 is how R3 applies to portal principals.
- **N3 (family email).** §10.
- **S5 (revocation by the next request).** §5.
- **The RFC's portal-only test fixtures** (an `EditSelf` user with dormant staff flags) no longer apply after the cutover, because no such accounts are left. The portal-principal tests in §17 cover the same ground.

## 16. Delivery plan

**First release (one release, ships together).** Each row is one issue and one PR, built behind `bMemberSignIn` and released together. Without the cutover, members would have two ways in. Without email sign-in, the cutover would lock them out.

| # | Work | Needs |
|---|---|---|
| 0 | #10197: throttling and uniform responses (already filed) | — |
| 1 | Portal principal, the shared step, `PortalSessionMiddleware` and the audit table. The portal reads the principal instead of `getCurrentUser()` | — |
| 2 | Portal permissions model (§8): registry, `decide()`, `scopesFor()`, middleware, Twig function, nav. The core catalog with the adult and team-leader rules, and the existing portal pages moved onto it | 1 |
| 3 | Plugin API (§8.5): the manifest's `portal` block, `getPortalRules()`, `getPortalNavItems()`, `portalRoutesFile`, portal templates, and the plugin README and skeleton | 2 |
| 4 | Blocks and the member rule (§4), the Sign-in tab settings, and the Disable sign-in field | 1 |
| 5 | `/portal/login`, email code and link, throttles, the office list and notices | 0, 4 |
| 6 | Sign-in pages (§7): web root, `/admin` as the staff sign-in, the redirect from `/session/begin`, the links in `src/` and the Cypress login command; staff/portal switching (§6.4) | 5 |
| 7 | Remembered devices and the member's Sign-in and devices page | 5 |
| 8 | Passkeys | 7 |
| 9 | Admin lists, audit view, Statistics on the new tables, the Permissions tab, the person-page card and given grants | 2, 4, 5 |
| 10 | Email-change rules (§9) | 1, 5 |
| 11 | Family email moves to people (§10): the SQL in the 7.9.0 upgrade, every reader listed in §2, the directory, CSV, the API and docs | — |
| 12 | Login as User (§6.5), replacing #10392 | 1, 9 |
| 13 | Cutover: upgrade script, staff-only Users list, removing "Self-service only", wording, release notes | 1–12 |
| 14 | Documentation: a docs tracking issue and a docs PR on `release-docs/<version>` | 13 |

Row 11 doesn't depend on the sign-in work. It can be built first, and can ship one release earlier if that release comes sooner.

**Later.**

| # | Work | Needs |
|---|---|---|
| 15 | Google and Microsoft sign-in (§6.6) | 5 |
| 16 | Drop `fam_Email` | 11, one release later |

**Separate proposals:**
- the mobile app and its API (George);
- #10511 taking over `decide()`.

## 17. Tests (Cypress)

- **Email sign-in:**
  - a matching person gets one email;
  - the link opens a confirm page, and GET changes nothing;
  - confirm signs in, and the link can't be used twice;
  - the code works only in the browser that asked for it;
  - five wrong codes cancel the request;
  - a new request cancels the old one;
  - an expired request is refused.
- **Refusals:**
  - shared email, deceased, inactive, family deactivated, awaiting review, Disable sign-in, classification not ticked, under the minimum age;
  - each one shows the same answer screen, sends no email to the person, writes the reason to the audit, and sends one office notice;
  - a repeat within the hour sends no second notice.
- **Throttling:** per address, per IP and per person.
- **Sessions:**
  - the idle timeout;
  - Disable sign-in ends an open session on the next request;
  - a rule change does the same;
  - so does "sign out everywhere".
- **Remembered devices:**
  - the box is never ticked when the page loads;
  - without it, closing the browser or going idle ends the session, and nothing is kept on the device;
  - with it, the session comes back after idling;
  - the token is replaced at each use;
  - removing a device stops it;
  - Disable sign-in removes all of them.
- **Passkeys:**
  - the offer after sign-in appears only when the member ticked **This is my own device**;
  - adding one needs a recent email sign-in;
  - a passkey signs in;
  - a removed passkey fails;
  - Disable sign-in removes them all.
  - These use Chrome's virtual authenticator.
- **Sign-in pages:**
  - the web root goes to `/portal/login`;
  - `/session/begin` redirects to `/admin`;
  - a member session can't open any staff route;
  - a staff session goes to the portal and back without signing in again;
  - a member session's Admin Console link goes to the `/admin` sign-in.
- **Portal permissions:**
  - deny for an unknown capability, and for a capability whose plugin is off or whose feature switch is off;
  - a team leader manages only their own team, and gets 404 for another team;
  - the capability goes away when the leader is removed from the team;
  - a non-adult sees no family contact details;
  - a given grant works until its end date;
  - a test plugin's capability, rule and page work.
- **Email changes:**
  - every row of §9, including the notice to the old address and sessions ending;
  - an unconfirmed address can't sign in until one of the three ways confirms it, and each way is tested;
  - an address set by staff, an import or the API before the person's first sign-in signs in at once, and the audit records who set it;
  - saving a moved family address again doesn't confirm it.
- **Family email move:**
  - copied to the head, or to the spouse when the head has an email;
  - a timeline note when both have one, when there is no adult, and when another family holds the address;
  - nothing at all when a family member already has it;
  - in a family of two or more, a moved address doesn't sign in until staff with Edit Records or an administrator confirm it;
  - staff with Edit Records can't confirm a replacement address after the first sign-in, nor any address of a person with given portal grants or a staff account;
  - in a one-person family it signs in at once, as an address on record, and the audit records it;
  - mail for a family goes to the head;
  - the directory prints no family email.
- **Login as User:**
  - grayed out for deceased, Disable sign-in and staff accounts;
  - still starts, and stays open across later pages, for each case §6.5 deliberately doesn't check: an inactive person, a deactivated family, awaiting review, outside the member rule, no usable email, and member sign-in off;
  - each of the three checks refuses a start, and ends an open session on the next request;
  - hidden with the setting off;
  - read-only unless writes are allowed;
  - every write is recorded with both people;
  - Stop returns to the staff session.
- **Cutover:**
  - an upgraded database with self-service accounts loses them, and their calendar feed URLs keep working;
  - an `EditSelf` user with dormant staff flags is treated the same way;
  - administrators and read-only staff are untouched.
- **Audit:** no code, token or link appears in any row.

## 18. Not in scope

- A mobile app.
- Passwords for members.
- A member directory in the portal.
- Changing how staff sign in, apart from moving the page to `/admin`.
- An administrator giving a capability to a whole group at once.
- Portal roles as named bundles (#10511 role templates).
