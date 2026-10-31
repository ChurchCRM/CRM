# RFC: Email newsletters on Mailchimp and other providers

> **BLUF**
> A church connects several named accounts with newsletter services (for example, three Mailchimp accounts and two Constant Contact accounts). It defines any number of **Newsletters**, each tied to one account and audience. Every newsletter has a ChurchCRM group, "(name) Subscribers", whose roles show who is subscribed, waiting to confirm or unsubscribed. Staff change subscriptions in ChurchCRM, and members change their own in the Member Portal. Changes made in the provider come back by **Sync**. Mailchimp becomes the first provider plugin behind a core newsletter contract, and Constant Contact is the second. This builds on the 7.8.3 member sign-in work (email belongs to people, portal permissions) and on RFC 0005's provider registry shape. A maintainer accepted the direction with changes on 2026-10-29: webhooks and credential encryption are set aside for their own RFCs (§9). No release is committed.

**Status:** Accepted with changes (maintainer decision on #10885, 2026-10-29); no release commitment  
**Date:** 2026-10-24  
**Owner:** @cwhorton  
**Proposal issue:** [#10884](https://github.com/ChurchCRM/CRM/issues/10884) · **Depends on:** the [member sign-in design](https://github.com/ChurchCRM/CRM/pull/10841) (#10660, 7.8.3), [RFC 0005](0005-online-payments.md) §2.4 (registry shape) · **Related:** [#852](https://github.com/ChurchCRM/CRM/issues/852), [#2297](https://github.com/ChurchCRM/CRM/issues/2297), [#5764](https://github.com/ChurchCRM/CRM/issues/5764), [#456](https://github.com/ChurchCRM/CRM/issues/456), [#8152](https://github.com/ChurchCRM/CRM/issues/8152), [#10542](https://github.com/ChurchCRM/CRM/issues/10542), [#10550](https://github.com/ChurchCRM/CRM/issues/10550)  
**Numbering:** `0008` is provisional (`0006` and `0007` are on master). A maintainer assigns the final number on merge.

## Summary
- **Several accounts per provider.** Some churches have several people who each run a newsletter on an account they manage. Mailchimp charges more for extra audiences. ChurchCRM should connect to all of those accounts, not force them into one.
- **A newsletter is account + audience + interest.** Several newsletters can share one Mailchimp audience. Each one is a Mailchimp *group* (an "interest"), so readers choose which newsletters they get, and staff can filter by newsletter in Mailchimp.
- **A group per newsletter.** Each newsletter creates a group of a new type, **Email Newsletter**, named "(name) Subscribers". Its roles are **Member**, **Pending** and **Unsubscribed**. Leaving a newsletter changes the role; it doesn't remove the person, which keeps the record that they said no.
- **Subscriptions are per person.** After 7.8.3, email belongs to people: families have no email address. A subscription follows the person's own email.
- **A subscriptions table** is the source of truth and the consent record. The group shows its state.
- **Changes go both ways.** A change made in ChurchCRM is queued and sent to the provider. A change made in the provider comes back by Sync, on a button and on the timer jobs.
- **Members choose in the Member Portal**, under a portal capability from the member sign-in permissions model. The **Default** newsletter gets a **Join our newsletter** button.
- **One provider contract in core.** Mailchimp and Constant Contact are plugins that register a provider with it, using RFC 0005's registry shape.
- **The current Mailchimp plugin is migrated in the upgrade.** Its API key becomes the first account.

## Maintainer decision (2026-10-29)
The direction is accepted with changes ([decision on #10885](https://github.com/ChurchCRM/CRM/pull/10885)).

**Accepted**
- The shared newsletter work moves into core, and plugins implement it. Mailchimp is the first provider, and its settings are migrated (A1, A13). Campaigns stay written and sent in the provider.
- Newsletter Accounts in their own table (A2).
- Locked newsletter groups that change only through the newsletter service (A3).
- The missing group plugin events are filed as their own enhancement issue (§6).

**Set aside for their own RFCs** (§9)
- **Webhooks.** ChurchCRM doesn't support them today. If they are needed, they get their own RFC, built on a framework rather than hand-rolled. Until then, Sync is the only way provider changes come back (A5, A6).
- **Credential encryption as proposed.** Real protection needs a key unique to each install, which belongs in its own key-safety RFC. Until then, credentials are stored as plugin passwords are today (A7).

**Status**
- Direction accepted. No code yet. The child issues in §7 are not filed yet.
- **Comes after the member sign-in work (7.8.3).** It relies on family email moving to the people (design §10), proven and unconfirmed addresses (§9), the portal permissions model (§8) and Login as Person (§6.5). Those are under review as PRs into `feature/member-sign-in` (epic #10660, PRs #10844–#10859); section numbers refer to the design as revised in #10841. Code from that work is cited from those PRs.
- **Shares RFC 0005's provider registry shape** (A20, §2.4). If RFC 0005 lands first, this RFC reuses its registry. If this RFC lands first, it builds the registry in the shape RFC 0005 describes.
- No open issue proposes multiple newsletters or interests. Closed issues touched the edges: #852 (Mailchimp segments from group roles), #2297 (families or individuals: individuals, D10), #5764 (the newsletter flag is per family only) and #456 (email sync).
- RFC 0005's D4 removed newsletter sign-up from giving on 2026-10-05, because ChurchCRM had no email newsletter to sign up for. This RFC is where it would come back.
- #10542 (staff-composed email, replies and history) is separate. This RFC sends no mail through ChurchCRM's SMTP server.
- Code is cited by symbol on upstream `master` as of 2026-10-24 (`f0524f646`), unless marked as member sign-in.

**Today**
- `fam_SendNewsLetter` is a per-family yes/no flag. It drives the printed newsletter labels (`/people/labels/newsletter`) and the confirm report.
- The core plugin `src/plugins/core/mailchimp` (added in 7.0.0, library `drewm/mailchimp-api`) takes one API key.
  - The `defaultListId` setting is never read.
  - `MailChimpPlugin::getGroupMailChimpListId()` always returns null, so the group handlers (`onGroupMemberAdded`, `onGroupMemberRemoved`) never do anything. The group sync that `plugin.json` describes doesn't exist.
  - What works: the dashboard and the missing and unsubscribed list views; changing an email address in every list (`updateSubscriberEmail`); and on person delete, `unsubscribeFromAllLists`. That last one records an unsubscribe the person never made.
  - The family view's Mailchimp status reads the family email. The member sign-in work changes it to `Family::getEmailRecipient()` (head of household, else spouse).
- Plugin settings are stored flat as `plugin.{id}.{key}` in `config_cfg`. `PluginManager::getPluginSettingsWithValues()` keeps a `password` value out of the HTML, but the value is stored as plain text.
- `defuse/php-encryption` is already a dependency, used for 2FA secrets (`User.php`), but its key `sTwoFASecretKey` lives in `config_cfg`, so it protects nothing in a database dump.
- `EmailQueueService` (#10356) is the pattern for outgoing work: rows claimed with a conditional UPDATE, bounded runs from the web and from cron, and retries with delays.
- `Hooks::CRON_RUN` lets plugins run on the timer jobs.

**How to read this**
- Ids: **D** proposed product choice; **A** proposed technical choice; **F** flow (§4); **Q** open question (§8); **NL-nn** a proposed child PR (§7), not filed.
- "Provider" means a newsletter service (Mailchimp, Constant Contact). "Account" means one set of credentials for a provider. "Audience" is Mailchimp's term for a contact list (Constant Contact calls it a list).
- "Contact" is the person's record at the provider.
- "Proven" and "unconfirmed" addresses are the member sign-in design's terms (§9 there).
- Table short names (§3): `nla` = `newsletter_account_nla`, `nl` = `newsletter_nl`, `nls` = `newsletter_subscription_nls`, `nle` = `newsletter_event_nle`, `nlo` = `newsletter_outbox_nlo`.

---

## 1. Goals and non-goals

**Goals**
1. Several named accounts per provider; several newsletters per account.
2. Staff see and change who gets each newsletter from ChurchCRM: a newsletter page, the group, and a card on the person view.
3. Members join and leave newsletters in the Member Portal.
4. Changes made in the provider (a sign-up form, the unsubscribe link, the preferences page) reach ChurchCRM.
5. A consent record per person and newsletter: who changed it, when, and where.
6. Mailchimp in the first release, with Constant Contact proving the contract.
7. Churches already using the Mailchimp plugin lose nothing at upgrade.

**Non-goals (v1):** writing or sending newsletters from ChurchCRM (campaigns stay in the provider); a public sign-up form outside the portal (Q3); family-level subscriptions; replacing `fam_SendNewsLetter` or the printed labels; sending staff email through a provider.

---

## Proposed product choices

| # | Choice |
|---|---|
| D1 | **Named accounts.** An admin adds accounts on **Communication > Admin > Newsletter Accounts**. Each one has a name ("Youth – Jane's Mailchimp"), a provider and its credentials. A church can have any number of accounts for each provider. |
| D2 | **A newsletter is account + audience + interest.** Staff create newsletters on **Communication > Newsletters**. They pick an account and an audience, and either an existing Mailchimp group or a new one that ChurchCRM creates. Several newsletters can share an audience, so a church doesn't pay for extra audiences. Mailchimp allows about 60 groups per audience (to be checked in NL-05). |
| D3 | **Interests, not tags.** Mailchimp tags are visible only to staff, and a Mailchimp sign-up form submits no tags. Groups show on sign-up forms, readers can change them on Mailchimp's update-preferences page, and Sync reads them back. Staff can still filter by group in Mailchimp. Tags stay free for the church's own use. |
| D4 | **A group per newsletter.** Creating a newsletter creates a group of type **Email Newsletter**, named "(newsletter name) Subscribers", with roles **Member**, **Pending** and **Unsubscribed**. Pending is for double opt-in (D7). Renaming the newsletter renames the group. |
| D5 | **Leaving keeps the record.** Leaving a newsletter moves the person to Unsubscribed. They stay in the group, so Sync never adds them back. |
| D6 | **Unsubscribing in the provider applies to the whole audience.** Mailchimp's unsubscribe link, which is in every email, takes the contact off the whole audience. ChurchCRM marks the person Unsubscribed on **every newsletter that shares that audience**. Leaving one newsletter in ChurchCRM removes only that newsletter's interest. When the **address** is then on no newsletter in that audience, ChurchCRM archives the contact, because archived contacts don't count toward the bill. The check is by address across the whole audience, never by person, because a shared address (D12) is one contact. |
| D7 | **Staff adds need consent.** When staff add someone, ChurchCRM sends the contact as *pending*, and Mailchimp emails them a confirmation link. The role is Pending until they confirm. Staff can skip this by ticking "They asked to join", which is recorded with the change. Mailchimp's terms require permission, and some readers will have unsubscribed before. |
| D8 | **Members choose for themselves.** A new core portal capability, `portal.newsletters.manage` (no scope), is held by everyone signed in who has their own email, while at least one newsletter is marked "Offer in the Member Portal". It is a nav item and a **Newsletters** tab on the Profile page, where each offered newsletter has a Join or Leave button. The **Default** newsletter also gets a **Join our newsletter** button on the portal home page until the member joins. A member joining with a proven address is subscribed directly, because they signed in with that mailbox and asked. Otherwise the join is Pending (F2). |
| D9 | **Do Not Email.** A person with the `iDoNotEmailPropertyId` property can't be added by staff or by Sync, and a provider sign-up never makes them Member or Pending (F5, F8). In the portal the Join buttons are replaced by "Contact the office". Whether setting the property also unsubscribes them is Q2. |
| D10 | **Individuals, not families.** Subscriptions are per person and use the person's own email, `per_Email`. After 7.8.3 families have no email address: the upgrade moves each family email to the head of household or spouse (member sign-in §10). Work email isn't used, matching the bulk email lists (`PersonService::buildDoNotEmailSet`). This answers #2297. |
| D11 | **Unknown sign-ups aren't added as people.** When an address that matches no person signs up in the provider, it is listed on the newsletter page under **Not matched**. Staff can link it to a person or dismiss it. ChurchCRM never creates a person from a newsletter sign-up, because Mailchimp often has no name for them. |
| D12 | **Shared addresses** are rarer after 7.8.3, because the family email no longer stands in for everyone, and the Duplicate Emails report lists the personal emails people share. When two people still share one, the provider has one contact. ChurchCRM links it to the first match from `EmailLogService::resolveAddress()`. The other person's card on the person view says "Gets it at (shared address) through (name)". When the linked person stops using the address (A8) or is deleted (A9), the subscriptions stay with the address. They are relinked to the next person who still has it, by the same rule, with an `nle` row for each. Nothing is archived or sent, because the contact hasn't changed. Neither person can sign in with a shared address (member sign-in §9), so the portal never has to decide whose subscription it is. |
| D13 | **The person view** gets a **Newsletters** card with the person's newsletters and a Join or Leave action for each, replacing the plugin's Mailchimp tab. Its history link shows the consent record (`nle`). |
| D14 | **Sync.** Each newsletter has a Sync button, and Sync also runs on the timer (A6). Where the two sides disagree, the most recent change wins. ChurchCRM never overrides an unsubscribe or a "cleaned" (bounced) status that came from the provider: it can only send a new confirmation request (D7). |
| D15 | **Deleting a newsletter** deletes its group and its `nls` rows, keeps its `nle` history for the retention period (A10), and leaves the interest and contacts in the provider alone. The page says so before the delete. |
| D16 | **Who manages what.** An admin manages accounts. Staff with the Email permission (`bEmailMailto`, the permission the current Mailchimp menu uses) manage newsletters and subscribers. Whether each account should get owners who alone can see its newsletters is Q1. |
| D17 | **`fam_SendNewsLetter` stays** as the printed-newsletter flag. It doesn't affect email newsletters. |

## Proposed technical choices

| # | Choice | Reason |
|---|---|---|
| A1 | **Contract and registry in core.** `ChurchCRM\Newsletter\Provider\NewsletterProviderInterface` and `NewsletterProviderRegistry::register(PluginInterface $owner, NewsletterProviderInterface $provider)`, called from the plugin's `boot()`. The checks are RFC 0005's (§2.4): verified after plugins finish loading; the owner must be the loaded instance; the provider id must equal the plugin id; "core" is decided by path; a community plugin needs the new permission `newsletter.provider` in `ApprovedPluginRegistry::KNOWN_PERMISSIONS`; and a community id can't equal a core id. If both RFCs land, the two registries share one generic `ProviderRegistry`. | Core today finds plugins by hard-coded id plus `method_exists`. A registry lets one plugin provide more than one thing (a "type" per plugin), and an explicit call bound to its owner can't be replaced by another plugin the way a filter can. |
| A2 | **Accounts in `nla`, not plugin settings.** Credentials are one JSON value per account, stored as A7 describes. A provider can hand back updated credentials, for example a rotated OAuth refresh token, and the service saves them. | Plugin settings are one flat `plugin.{id}.{key}` set (50-character keys) and can't hold several accounts. Constant Contact's API uses OAuth tokens that are refreshed over time (to be confirmed in NL-08). |
| A3 | **Locked groups and a managed write.** Membership rows of an Email Newsletter group can be written only inside `NewsletterService`'s write context. The Propel pre-save and pre-delete checks refuse anything else. The volunteer pool already works this way (`VolunteerPoolWriter`, design D19). The group page, cart and **Add to group** send staff to the newsletter page, which has its own **Add cart** button. Renaming the group, deleting it, or editing its roles is refused too. The type is looked up by name, like `VolunteerMinistryService::ministryGroupTypeId()`. | `GroupService::addUserToGroup()` (used by `Cart::emptyToGroup`) and `removeUserFromGroup()` (raw SQL, used by `Person::preDelete`) skip hooks. A locked group can't drift from `nls`, and the newsletter doesn't depend on §6. |
| A4 | **Nothing calls the provider during a page request.** Every outbound change is written to `nlo` in the same transaction as `nls`. `NewsletterOutboxService` sends it the way `EmailQueueService` sends mail: each row claimed with a conditional UPDATE; bounded runs from the timer jobs only, never inside the request that made the change: cron (`cli/timerjobs.php`), or on installs without cron the browser's separate background request (`POST /api/background/timerjobs`); retries with growing delays; a refused change fails at once. **Every change is fenced by the subscription's version.** `nls_Version` goes up by one on every change to a subscription's status or address (`nls_Email`, A8), from any source, and each `nlo` row records the version it was made from (`nlo_NlsVersion`). When a row is claimed, a version that no longer matches marks it `superseded`, and it isn't sent. Mailchimp's API has no conditional write, so a provider-side change (F4, F6) can still land while a row is `sending`. After the provider call returns, the sender therefore compares versions again, in the transaction that marks the row `done`. If `nls` moved on during the send, it re-reads the contact. If the provider's state no longer matches `nls`, it queues one corrective row from the current version. That row never re-subscribes a contact the provider marked unsubscribed or cleaned (D14); Mailchimp refuses that anyway. Sync (F6) is the backstop for anything this misses. Changes for one audience go together, as a Mailchimp batch when there are more than 50. The newsletter page shows how many changes are still waiting, and any errors. | A 200-person cart would otherwise mean 200 API calls in one request. Failures are retried instead of lost, and two runs never send the same change. |
| A5 | **No webhooks.** Changes made in the provider reach ChurchCRM only through Sync (A6). A webhook system is set aside for its own RFC, built on a framework (§9). | ChurchCRM has no webhook support today, and Sync works for every provider. |
| A6 | **Sync is "changes since".** The required provider method is `changesSince(audience, DateTimeImmutable $since)`, using Mailchimp's `since_last_changed` and Constant Contact's `updated_after`. Sync runs from the timer jobs every hour by default (a setting) and from the button. It is the only way provider changes come back (A5). | It works the same for every provider. RFC 0005 does the same with `eventsSince` plus reconcile. |
| A7 | **Credentials are stored as plugin passwords are today.** They live in `nla_Credentials`, are never sent to the browser, and only admins can see or replace them. They aren't encrypted at rest: that is set aside for a key-safety RFC with a key unique to each install (§9), which this table can adopt without changing its shape. | It's the same protection `sSMTPPass` and plugin passwords have today. Encrypting with a key that isn't unique to the install wouldn't protect them. |
| A8 | **Email changes.** When a person's `per_Email` changes, ChurchCRM first checks whether another person still has the old address (D12). If someone does, the subscriptions are relinked to that person and nothing is sent. Otherwise ChurchCRM updates `nls_Email`, which raises `nls_Version` so any change still queued for the old address is superseded (A4). It then queues an email change for each row (Mailchimp: `PATCH` the member's `email_address`), as `updateSubscriberEmail` does today. Clearing the email queues an archive, but only when no other person has the address and the address is on no other newsletter in that audience (D6). Under member sign-in, a member's own change happens only when they use the confirmation link (§9 there), so the newsletter follows the address only once it is the person's email. An unconfirmed address set by staff is the person's email for mail at once, so the newsletter follows it too. | Keeps the provider's history on the contact instead of creating a new one. |
| A9 | **Person delete** relinks the subscriptions to another person who still has the address (D12). Otherwise it archives the contact, under the same audience-wide address check as D6. It doesn't unsubscribe them. | An unsubscribe records a choice the person never made. Archiving also takes the contact off the bill. |
| A10 | **Consent record.** `nle` is append-only. Each row holds the person, the address, the newsletter, the old and new status, the source (`staff`, `portal`, `provider`, `sync`, `import`, `upgrade`), the user who made the change, the Login as Person session when there is one, and "They asked to join". It is kept for 7 years by default (`iNewsletterEventRetentionYears`). | Proof of consent for the provider's terms and for privacy requests. |
| A11 | **Role-aware group email.** `PersonService::getGroupMailingEmails()` keeps returning roles in `byRole`. For Email Newsletter groups it returns only Member, so "email this group" never reaches Unsubscribed or Pending people. | The method ignores role today. Whether the composer should offer newsletter groups at all is Q6. |
| A12 | **Initial import.** When a newsletter is created on an existing interest, ChurchCRM reads the audience once. Matched contacts become `nls` rows with source `import`, and the rest go to Not matched. Nothing is sent back. | A newsletter that already exists in Mailchimp starts from what Mailchimp holds. |
| A13 | **Migrating the current plugin in the upgrade.** The upgrade that ships this work runs a PHP step after its SQL, as `7.8.1.php` does. If `plugin.mailchimp.apiKey` is set, the step creates an account named "Mailchimp", moves the key into it (A7) and clears the old setting. If `defaultListId` is set, the step creates no newsletter: it adds a notice on the Newsletters page that names the audience and offers **Create a newsletter for it**. If the plugin was enabled, it stays enabled. The dashboard and list views move to the account page as account tools, and the stub group handlers and the unused setting go away. | Churches already using the plugin lose nothing, and nobody re-enters a key. Because ChurchCRM upgrades itself on the first page load, nobody can do this by hand beforehand. A newsletter needs an interest, which the old settings never had, so staff choose it. |
| A14 | **Outbound hosts are fixed.** Mailchimp calls go only to `https://{dc}.api.mailchimp.com`, with the data center taken from the key and checked against `^us\d+$`. Constant Contact calls go to its fixed API host. | Stops a crafted key or setting from sending credentials to another host. |
| A15 | **Portal pages are core.** The Newsletters tab and the home button are core portal pages, not plugin portal routes. They check `portal.newsletters.manage` with `RequirePortalCapability`, and Join and Leave are POST requests. Read-only Login as Person can open the tab but can't change anything. A Login as Person session that may write is recorded in `login_as_person_action_lpa` and in `nle`. | Newsletters belong to core and the providers are plugins, so the member's view stays the same whichever provider a newsletter uses. |

---

## 2. Architecture

### 2.1 Layers

```
Staff  /newsletters, /newsletters/{id}        Admin /admin/newsletter-accounts     Portal /portal/profile (Newsletters tab), home button
API    /api/newsletters/*  (Email permission) /api/newsletter-accounts/* (Admin)   /api/portal/newsletters/* (portal.newsletters.manage)
                         └──────────────┬───────────────┘
CORE ChurchCRM\Newsletter
  NewsletterService ──► nls + nle + nlo (one transaction) ──► group membership (managed write, A3)
  NewsletterOutboxService (timer jobs only) ──► provider->apply(changes)
  NewsletterSyncService ──► provider->changesSince() ──► NewsletterService / Not matched
CORE ChurchCRM\Newsletter\Provider: NewsletterProviderInterface, ProviderCapabilities, DTOs, NewsletterProviderRegistry
        ▲ register($this, $provider) in boot()
PLUGINS src/plugins/core/mailchimp (rewritten), src/plugins/core/constant-contact (new)
        plugin.json: permissions network.outbound secrets.store newsletter.provider
```

### 2.2 Contract (sketch)

```php
interface NewsletterProviderInterface
{
    public function id(): string;                         // equals the plugin id, 'mailchimp'
    public function capabilities(): ProviderCapabilities; // interests, pendingOptIn, batch
    public function credentialFields(): array;            // rendered on the account form; never sent back to the browser
    public function testConnection(AccountCredentials $c): ConnectionResult;
    public function listAudiences(AccountCredentials $c): array;            // AudienceRef[]
    public function listInterests(AccountCredentials $c, string $audienceId): array;
    public function createInterest(AccountCredentials $c, string $audienceId, string $name): string;
    public function getContact(AccountCredentials $c, string $audienceId, string $email): ?ContactState;
    public function apply(AccountCredentials $c, string $audienceId, array $changes): array; // ContactChange[] -> ChangeResult[]
    public function changesSince(AccountCredentials $c, string $audienceId, \DateTimeImmutable $since): iterable; // ContactState
}
```

`ContactState` holds the address, the provider status (`subscribed`, `pending`, `unsubscribed`, `cleaned`, `archived`), the interest ids and the last-changed time. Constant Contact maps its list membership and its permission-to-send status onto the same fields. A `ChangeResult` may carry updated credentials (A2).

## 3. Data model

| Table | Columns (main) | Notes |
|---|---|---|
| `newsletter_account_nla` | `nla_ID`, `nla_Provider` (varchar 50), `nla_Name` (varchar 100, unique), `nla_Credentials` (text, JSON, A7), `nla_Active`, `nla_CreatedBy`, `nla_DateEntered` | One row per account. |
| `newsletter_nl` | `nl_ID`, `nl_nla_ID`, `nl_AudienceID`, `nl_InterestID`, `nl_Name`, `nl_grp_ID` (unique), `nl_IsDefault`, `nl_InPortal`, `nl_LastSyncAt`, `nl_LastSyncCursor` | At most one row with `nl_IsDefault = 1`. Unique on (account, audience, interest). |
| `newsletter_subscription_nls` | `nls_ID`, `nls_nl_ID`, `nls_per_ID` (NULL = not matched), `nls_Email`, `nls_Status` (`member`, `pending`, `unsubscribed`, `cleaned`), `nls_Version` (A4), `nls_ChangedAt`, `nls_Source`, `nls_Dismissed`, `nls_Blocked` (Do Not Email, F5) | Unique on (newsletter, email). Current state. A row with no person is a Not matched entry. `nls_per_ID` is set to NULL when the person is deleted (A9). |
| `newsletter_event_nle` | `nle_ID`, `nle_nl_ID`, `nle_per_ID`, `nle_Email`, `nle_From`, `nle_To`, `nle_Source`, `nle_UserID`, `nle_lps_ID` (Login as Person session, NULL), `nle_AskedToJoin`, `nle_At` | Append-only consent record (A10). |
| `newsletter_outbox_nlo` | `nlo_ID`, `nlo_nl_ID`, `nlo_Email`, `nlo_Change` (JSON), `nlo_NlsVersion` (A4), `nlo_Status` (`queued`, `sending`, `done`, `failed`, `superseded`), `nlo_Attempts`, `nlo_NextAttemptAt`, `nlo_ClaimedAt`, `nlo_LastError` | Sent as in A4. Shown on the newsletter page when it fails. |

Group roles map one to one: `member` → Member, `pending` → Pending, and `unsubscribed` or `cleaned` → Unsubscribed. A row with no person has no group membership.

The tables, the group type and the account migration (A13) go in the release's upgrade script, named for the release a maintainer schedules it into (as RFC 0005 question 4 proposes). The same tables go in `Install.sql`.

## 4. Flows

| # | Flow |
|---|---|
| F1 | **Staff add a person.** They choose Add on the newsletter page or the person card. If Do Not Email is set or the person has no email, the add is refused. Otherwise ChurchCRM writes an `nls` row (Pending, or Member if "They asked to join" is ticked), an `nle` row, the group role and an `nlo` change. The outbox upserts the contact with the newsletter's interest, using status `pending` or `subscribed`. **Add cart** does the same for each person in the cart and reports who was skipped and why. |
| F2 | **A member joins in the portal.** Same as F1, with source `portal`. If the member's email is proven, the status is Member and the outbox sends `subscribed`. If it isn't proven yet (an unconfirmed address, or one the member hasn't signed in with), the status is Pending and the outbox sends `pending`. If the contact unsubscribed at the provider earlier, Mailchimp refuses `subscribed` ("Member In Compliance State"), so the outbox sends `pending` and the role becomes Pending. The tab then says "Check your email to confirm". |
| F3 | **Leaving one newsletter** (staff or portal). The person's status becomes Unsubscribed, and the outbox removes the interest. If the address is then on no newsletter in that audience, the outbox also archives the contact (D6). |
| F4 | **Unsubscribe link or a bounce, from the provider.** Sync finds the change. ChurchCRM reads the contact (A6), sees `unsubscribed` or `cleaned`, and marks the person Unsubscribed on every newsletter on that audience, with source `provider`. In the same transaction it marks that person's pending outbox entries for those newsletters `superseded`. Nothing goes back to the provider. |
| F5 | **Sign-up or preference change on a Mailchimp form.** Sync finds the change. ChurchCRM reads the contact and compares its interests with each newsletter on the audience. A matched address gets one row for each newsletter, by the provider's status: `subscribed` with the interest → Member; `pending` → Pending, kept until the reader confirms; `unsubscribed`, `cleaned`, `archived`, or the interest removed → Unsubscribed. An unmatched address gets a Not matched row (D11). **Do Not Email (D9):** if the matched person has the property, a status that would make them Member or Pending isn't applied to them. The row is kept with no person and no group role, flagged `nls_Blocked`, and listed under Not matched with the reason "Do Not Email". Nothing is sent back, because the reader's own choice at the provider stands. Staff decide what to do: clear the property and link the row, or remove the contact in Mailchimp. A provider Unsubscribed for that person is still applied as usual. |
| F6 | **Sync.** For each audience, ChurchCRM calls `changesSince(lastCursor - 5 minutes)` and, for each contact, takes whichever side changed last (D14). Where the provider's state won, it marks conflicting pending outbox entries `superseded`; then it re-sends only the pending entries that still match `nls`. The cursor moves only after the whole page of results is saved. |
| F7 | **Email change or person delete.** See A8 and A9. |
| F8 | **Linking a Not matched address.** Staff pick a person, and the row gets that person and the group role. Linking is refused for a person with Do Not Email while the row is Member or Pending (D9). Nothing is sent to the provider, because it already has the contact. |
| F9 | **Upgrade.** See A13. The first Sync of a migrated account lists the audience's contacts under the notice; nothing is added to a group until staff create a newsletter. |

## 5. Security and privacy
- **Credentials** are stored as plugin passwords are today (A7): never sent to the browser, admin only, and not encrypted at rest until the key-safety RFC (§9). The account form shows "Configured" and a Replace field.
- **Staff permissions.** Accounts are admin only. Newsletters and subscribers need the Email permission.
- **Portal.** A member sees and changes only their own subscriptions, through `portal.newsletters.manage` (A15). A member who shares an address (D12) can't sign in with it, so the portal never shows a subscription that belongs to someone else. Read-only Login as Person can't change anything.
- **Consent.** Every change is in `nle` with its source, the user who made it and any Login as Person session (A10). Staff adds default to double opt-in (D7), and a portal join from an address that isn't proven does too (F2).
- **Data at the provider.** ChurchCRM sends only the address, first name, last name and interests: Mailchimp's `FNAME` and `LNAME`, as the plugin sends today. It sends no phone, address or birthday.
- **Outbound hosts** are fixed (A14).
- **Retention.** `nle` is kept 7 years (a setting). Outbox rows are deleted 30 days after they're sent.

## 6. Group events for plugins (separate issue)

This isn't needed for newsletters (A3), but plugins asked for it, and the newsletter work turned up the gaps.

- **Keep the existing names.** `GROUP_MEMBER_ADDED` (`group.member.added`) and `GROUP_MEMBER_REMOVED` (`group.member.removed`) already exist, and renaming them would break plugins.
- **Add** `GROUP_CREATED`, `GROUP_UPDATED` (rename or type change), `GROUP_DELETED`, and `GROUP_MEMBER_ROLE_CHANGED`, which carries the membership, the group, the person and the old and new roles.
- **Fire them on every write path.** These change memberships today without firing a hook:
  - `Cart::emptyToGroup` → `GroupService::addUserToGroup()`;
  - `Person::preDelete` → `GroupService::removeUserFromGroup()` (raw SQL);
  - group delete (`Person2group2roleP2g2rQuery::create()->filterByGroupId()->delete()` in `api/routes/people/people-groups.php`);
  - the role change in the same file (`setRoleId`, no event);
  - role delete reassignments (`GroupService::deleteGroupRole()`);
  - `DemoDataService`.

  The volunteer services already fire the two existing events (`VolunteerMinistryService`, `VolunteerClassLinkService`). Fire the new ones from the services, or move them into the model's post-save; whichever is chosen, remove the calls in the routes so nothing fires twice.
- For `GROUP_DELETED`, decide between one event carrying the member ids and `GROUP_MEMBER_REMOVED` for each member. I recommend one event.
- Add `hooks.group` to `KNOWN_PERMISSIONS`, next to `hooks.person` and `hooks.family`.
- Document that a handler shouldn't make network calls inline (A4).

## 7. Proposed breakdown

| PR | What | Depends on |
|---|---|---|
| NL-01 | Provider registry (shared with RFC 0005 if both are accepted). | — |
| NL-02 | Group plugin events (§6). Its own issue. | — |
| NL-03 | Tables, the Email Newsletter group type, the managed write (A3), and role-aware `getGroupMailingEmails` (A11). | member sign-in (7.8.3) |
| NL-04 | Accounts page and API (admin), the Mailchimp provider with connection test, and the upgrade migration of the existing key (A13). | NL-01, NL-03 |
| NL-05 | Newsletters page: create (new or existing interest, with import, A12), Add and Leave, Add cart, Default and In-portal flags, outbox (A4). | NL-04 |
| NL-06 | Person view Newsletters card with history (D13); portal capability, Newsletters tab and home Join button (D8, A15). | NL-05 |
| NL-07 | Sync: timer and button, Not matched list, linking (F6, F8). | NL-05 |
| NL-08 | Constant Contact provider. | NL-07 |
| NL-09 | User and admin docs, plugin README and `plugin-development.md` changes. | NL-06 |

Each PR ships behind the plugin being enabled. Nothing appears in menus until an account exists, except the migration notice for a church that already used the plugin.

## 8. Open questions

| # | Question | Proposed default |
|---|---|---|
| Q1 | Should an account have owners, so that a youth minister sees only the youth newsletters? | No in v1: everyone with the Email permission sees every newsletter. Revisit with the RBAC work (RFC 0002). |
| Q2 | Should setting Do Not Email on someone also unsubscribe them from every newsletter? | No. It blocks new adds (D9), and the person card shows a warning while they still have subscriptions. |
| Q3 | Should there be a public sign-up page outside the portal? | Not in v1. Mailchimp's own form already covers this, and its sign-ups arrive through F5. |
| Q4 | What happens when an archived contact who had unsubscribed is added again? | To be checked against the Mailchimp API in NL-05. The expected answer is that only `pending` works (F2). |
| Q5 | ~~Should a household be able to subscribe as one?~~ | Settled by 7.8.3: email belongs to people (D10). |
| Q6 | Should the email composer be able to send to a newsletter group? | Yes, to Member only (A11). Sending a newsletter through ChurchCRM's SMTP server bypasses the provider's unsubscribe link, so the composer warns before it does. |
| Q7 | How do Constant Contact's OAuth tokens rotate? | To be checked in NL-08. |

## 9. Set aside for their own RFCs

Both need more thinking and a proper security review, so what eventually lands is really secure (maintainer decision, 2026-10-29).

- **Webhooks.** An RFC for a webhook framework: a public route, sender verification, replay protection and delivery bookkeeping, built on a framework rather than hand-rolled. Newsletter providers would plug into it. Until then, Sync covers every provider (A6).
- **Key safety.** An RFC for a key unique to each install, kept outside the database, and how credentials such as `nla_Credentials`, plugin passwords and `sSMTPPass` are encrypted with it. Until then, credentials are stored as they are today (A7).
