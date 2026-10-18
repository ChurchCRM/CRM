# RFC: Online payments and payment gateways

> **BLUF**
> A core gateway contract with Stripe, PayPal and BTCPay Server as core plugins. Members give once or on a schedule from the Member Portal, and visitors give from a public page, through hosted checkout, so no card data reaches ChurchCRM. Every gift lands once in the existing `pledge_plg` / `deposit_dep` tables. This RFC depends on [RFC 0004](0004-portal-giving-pledges.md) for per-person credit, the shared finance fixes and the portal Giving page; a payment can optionally fulfil a pledge from it. Five decisions are open for maintainers (below). No release is committed and no implementation issues are filed until the direction is accepted.

**Status:** Proposed, open for review; no release commitment  
**Date:** 2026-10-17  
**Owner:** @cwhorton  
**Proposal issue:** [#10033](https://github.com/ChurchCRM/CRM/issues/10033) · **Depends on:** [RFC 0004](0004-portal-giving-pledges.md) · **Related:** [#9313](https://github.com/ChurchCRM/CRM/issues/9313), [#10550](https://github.com/ChurchCRM/CRM/issues/10550)  
**Numbering:** `0005` is provisional; a maintainer assigns the final number on merge.

## Summary
- Members give once or on a schedule (weekly, monthly, annually) from a **Give** tab on the Member Portal Giving page that RFC 0004 adds, and see their giving by calendar year.
- Visitors give anonymously from a public page, with an optional email receipt.
- Stripe, PayPal and BTCPay Server are core plugins behind a gateway contract in core. v1 sends donors to each gateway's hosted page, so no card data reaches ChurchCRM.
- The gateway runs recurring charges and reports each one to a webhook served from the plugin's own folder.
- Every gift is recorded once into the existing `pledge_plg` / `deposit_dep` tables. There is no second ledger.
- Deposits (gross, fee, net), refunds, disputes, receipts, fundraiser balances and online reports build on RFC 0004's per-person credit, audit log, `PledgeWriter` and statement service.
- A payment can link to a pledge through a nullable pledge id, so **Pay my pledge** fulfils it (D22). A pledge stays the member's editable intent, and a gateway subscription stays a separate thing (A22).

## Questions for the maintainer
1. A core gateway contract plus core gateway plugins, revising #8178's adapter plugin (§2.1)?
2. Public webhook routes inside plugin folders, dispatched before `LoadConfigs.php` (§2.5)? Fallback: a core `POST /api/public/giving/webhook/{gatewayId}`.
3. Plugin-local `vendor/` built at release (D10, §2.6), or `stripe/stripe-php` in `src/composer.json`?
4. Migration scripts are written as `<next>-*.sql`, named for the release a maintainer schedules them into, and added to `upgrade.json` only then (§3)?
5. Phases B–D, F and GIV-50 before the donor surfaces, and phase E after RFC 0004's Giving page (GIV-83) (§7)?
6. Answered in #10033: the per-person attribution (GIV-05, GIV-06, GIV-07, GIV-43) is a prerequisite in RFC 0004, and #9313 stays its epic.

**Status**
- Proposed. No code yet. The child issues in §7 are opened only if this direction is accepted.
- Related: #9313 (per-person gifts), #8178, #8180 (plugin gateways), #9600, #9472, #9575, #8977 (Member Portal), #9225, #8181.
- Depends on RFC 0004 (prerequisites GIV-02 to GIV-07 and the Giving page GIV-83). RFC 0004 does not depend on this RFC.
- Builds on the Member Portal (`src/portal/`, epic #8977) and the email log (`EmailLogService`, #9876), both on master. Members sign in through the [member sign-in](https://github.com/ChurchCRM/CRM/blob/feature/member-sign-in/docs/design/member-sign-in.md) design accepted for 7.9.0 (#10464), with no user account. Login as User returns with it (§6.5 there), so the guards below apply to it.
- Code is cited by symbol on upstream `master` as of 2026-10-17.

**Today:** no gateway code (removed in #4566; dead Load Authorized and Run Transactions buttons in `DepositSlipEditor.php`). Per-person credit comes from RFC 0004.

**How to read this**
- Ids: **D** proposed product choice; **A** proposed technical choice; **F** flow (§4); **I** invariant (§3.3); **Q** open question (§8.1); **T** threat (§6); **R** requirement (Appendix A); **GIV-nn** a proposed child PR (§7), not filed.
- Ids are shared with RFC 0004. Items that moved there keep their numbers and are marked "Moved to RFC 0004" here: D3, D11, A10, A14, A21, A31, A32, I5, I6, F8 (office entry), GIV-02 to GIV-07, GIV-43. This RFC refers to them by the same id.
- Table short names (§3.2): `gint` = `giving_intent_gint`, `gtxn` = `giving_transaction_gtxn`, `grec` = `giving_recurring_grec`, `gcus` = `giving_customer_gcus`, `gwhe` = `giving_webhook_event_gwhe`, `gntf` = `giving_notification_gntf`, `gnc` = `giving_noncash_gnc`, `srl` = `system_ratelimit_srl`; `faud` = `finance_audit_faud` (RFC 0004).
- Terms: HoH = head of household; UQ = unique key; PI = Stripe PaymentIntent; FMV = fair market value of goods the donor received; ACH = US bank debit; TOTP = authenticator-app code; SAQ-A = the shortest PCI DSS self-assessment, for merchants that hand all card entry to a provider; FAQ 1588 = the PCI Security Standards Council FAQ on SAQ-A script criteria.

---

## 1. Goals and non-goals

**Goals**
1. Core gateway contract; Stripe, PayPal and BTCPay as core plugins, each serving its webhook from its own folder.
2. Every online gift credited to a person, a family or no one, from the portal or the public page (office entry: RFC 0004).
3. Two more tabs on RFC 0004's portal **Giving** page. **Give:** funds (split allowed), fee estimate and offer to cover, recurring (Monthly default; Weekly or Annually; chosen day) where supported, recurring notices with More Info and Cancel, Apple/Google Pay where the gateway allows, and Pay my pledge (D22). **Giving History:** by calendar year with totals, scoped by D3.
4. Public anonymous give page (D4).
5. The non-cash section of statements, portal statement downloads and online reports, on RFC 0004's statement service.
6. Fundraiser balances payable online.
7. Deposits with gross, fees and net; review queue; refunds; disputes; webhook health.
8. US-compliant receipts; crypto as non-cash property.

**Non-goals (v1):** a second ledger; multiple currencies; recurring on the public page; Omnipay (no subscriptions support); pledges themselves (RFC 0004); everything marked Defer or Reject in §9.

---

## Proposed product choices

| # | Choice |
|---|---|
| D1 | The first release ships **Stripe, PayPal and BTCPay Server** as gateway plugins. **Release gate:** online giving stays hidden until all three are merged. Reason: the contract is proven against three different payment models (card processor, PayPal account, self-hosted crypto) before any church relies on it, and a church picks its gateway once, because recurring gifts cannot be moved from one gateway to another. |
| D2 | Upstream epic. The Giving domain and gateway contract live in core. Gateways are core plugins in `src/plugins/core/{stripe,paypal,btcpay}/`. |
| D3 | Moved to RFC 0004 (HoH sees the whole family; everyone else, spouse included, sees only what is credited to them). This RFC applies it to History, notices and downloads. |
| D4 | The public page is anonymous. An optional email gets a receipt. The page links "If you are a member, click here" to the Give tab, `/portal/giving/give`, which requires login and returns the member there. **No newsletter sign-up** (removed 2026-10-05): ChurchCRM has no email newsletter to sign up for — only the family "Send Newsletter" flag, used for printed labels, and a Mailchimp plugin that reads audiences — so the giving pages do not add one. |
| D5 | v1 uses **hosted redirect** for all three gateways (Stripe Checkout, PayPal approval page, BTCPay checkout link). Apple Pay and Google Pay appear on Stripe's hosted page, including for recurring gifts. On-page wallet buttons come later (GIV-60). |
| D6 | **The first recurring charge falls on the chosen day**, not today unless today is that day (Stripe "Last day" chosen on a 28th–30th starts on the next last day, F3). The UI reads "First gift on Oct 1, then monthly on the 1st". Days 1–28, plus "Last day". |
| D7 | PayPal recurring uses **Subscriptions v1 REST**, the current replacement for PayPal's older NVP "Recurring Payment profiles". |
| D8 | Give, public and return pages **stay overridable** by themes like any portal template. With hosted redirect the PCI SAQ-A script criterion does not apply (FAQ 1588 covers embedded iframes, not redirects). Remaining control: the server builds the redirect URL and accepts only the gateway's hosts. Stricter script controls come only with GIV-60 and cover only a small payment partial, not the layout. |
| D9 | Deposits get **one new `dep_Type`, 'Online'**, no 'Crypto'. `dep_Gateway` separates BTCPay deposits. Crypto handling (non-cash, excluded from cash totals, no value on receipts) follows from `plg_method = 'CRYPTO'` and the row's `giving_noncash_gnc` record. |
| D10 | **Gateway SDKs live inside the plugin.** The loader optionally loads `src/plugins/<core\|community>/<id>/vendor/autoload.php`. Stripe's own `composer.json` holds `stripe/stripe-php`, which needs no other Composer package, so no version conflicts. The release build runs `composer install` in each plugin with a `composer.json`; Dependabot gets one entry per plugin. PayPal and BTCPay use core's Guzzle (`php-http/guzzle7-adapter` in `src/composer.json`). Why not `stripe/stripe-php` in `src/composer.json`: every install would ship and update a payment SDK even if it never takes online gifts, and a community gateway plugin could not bring its own SDK the same way. Cost: a loader change, a per-plugin install step in the release build, a CI duplicate-package check and one Dependabot entry per plugin (GIV-09). If this is refused, the fallback is `stripe/stripe-php` in `src/composer.json` (question 3). |
| D11 | Moved to RFC 0004 (adult child who starts a family). This RFC adds: new and recurring gifts go to the new family from the next charge, because the family is looked up at charge time. |
| D12 | This design is posted for feedback only, with one proposal issue. No epic or child issues are filed until the maintainer accepts the direction. |
| D22 | **Pay my pledge.** A pledge card from RFC 0004 offers Pay my pledge when the Give tab is available. It opens the Give tab with the pledge's fund and amount per gift: one-time, or recurring when the gateway supports the pledge's schedule (weekly or monthly). Each recorded row of that fund carries the pledge id. The pledge stays the member's editable intent and the recurring gift stays a separate thing (A22): editing or ending the pledge never changes the subscription, and cancelling the subscription never ends the pledge. |

## Proposed technical choices

| # | Choice | Reason |
|---|---|---|
| A1 | Hosted redirect in v1 (D5); embedded modes via per-gateway `CheckoutMode` in GIV-60. | SAQ-A; no CSP change in v1. |
| A2 | `giving_intent_gint` (attempt; source of truth for the fund split) and `giving_transaction_gtxn` (ledger). | Abandoned attempts stay out of the ledger. |
| A3 | Pledge rows link by `plg_gtxn_ID`; gateway GroupKeys start `gtxn{id}`. | Trivial immutability guard; GroupKey is never parsed. |
| A4 | Pledge columns stay `decimal(8,2)` (`plg_amount`, `plg_NonDeductible`); new tables use wider `decimal(10,2)`; every entry point caps a gift at 999,999.99; integer cents; 2-decimal currencies only. | No overflow when split into pledge rows. |
| A5 | One Stripe "ChurchCRM Gift" product per mode, priced inline; split kept locally. | No product per fund. |
| A6 | Weekly batch deposit per gateway; Stripe payout mode opt-in later (GIV-42). | Needs no payout event, so it works when webhooks arrive late or only through catch-up (F14). |
| A7 | `plg_method` adds only 'CRYPTO' (card, wallets, PayPal = CREDITCARD; ACH = BANKDRAFT); `dep_Type` adds only 'Online' (D9). | Payouts mix methods; smallest enum change. |
| A8 | `PluginWebhookApp` dispatched at the top of `src/plugins/index.php`, before `Include/LoadConfigs.php`, in a session-less boot mode. | No auth, session, `bLockURL` redirect or DB auto-upgrade in a callback. |
| A9 | Inline processing, 10 s budget, 503 on transient failure. | `fastcgi_finish_request()` exists on PHP-FPM and FrankenPHP (alias of `frankenphp_finish_request()`), not on Apache mod_php. |
| A10 | Moved to RFC 0004 (the D3 scope query). | |
| A11 | Refund rows dated on the refund date, in the open deposit; re-issue flag across years. | Closed periods never change. |
| A12 | Covered fee spread by largest remainder (leftover cents go to the lines with the largest fractional parts; ties to the larger line, then the lower fund id); designated fund optional. Each row stores its share in `plg_FeeCovered`. | Fund shares stay honest, and a covered fee stays a gift on top: it never pays down a fundraiser balance or counts toward the price of an item (F12). |
| A13 | Separate `gntf`, `gcus`, `gnc` tables. | Own lifecycles and retention. |
| A14 | Moved to RFC 0004 (core append-only `finance_audit_faud`). This RFC adds `faud_IpHash` (GIV-13). | |
| A15 | Encrypted `secret` type (Defuse, `defuse/php-encryption` in `src/composer.json`), key from `Include/Config.php` or env; live blocked without it. | A DB-stored key like `sTwoFASecretKey` (read in `Include/LoadConfigs.php`) protects nothing in a dump. |
| A16 | `RateLimitService` + `ClientIp` (trusted proxies); per-session, per-email, circuit breaker; high per-IP ceiling; Turnstile deferred to GIV-60. | Sunday Wi-Fi shares an IP; Turnstile needs the GIV-60 CSP builder. |
| A17 | Automatic deposits `dep_EnteredBy` NULL (the column is unsigned); system rows `plg_EditedBy = Person::ONLINE_GIVING (-3)` (the column is signed). | −3 sits beside the existing system ids `Person::SELF_REGISTER`/`Person::SELF_VERIFY`, so system writes are recognisable. |
| A18 | Default fees = standard published domestic rates. | International or currency-converted cards cost more (Stripe adds about 1.5% for international cards and 1% for conversion); any shortfall is the church's (F9). |
| A19 | Refunds and staff cancels need Finance + a **TOTP re-prompt** (`User::isTwoFACodeValid()`). | Enrolment is not presence. |
| A20 | `PaymentGatewayRegistry::register(PluginInterface $owner, PaymentGatewayInterface $g)`, not an open filter, verified after plugin loading ends; "core" decided by path; `payments.process` tag; CI flag `CHURCHCRM_GIVING_SANDBOX` for `FakeGateway` (§2.4). | A filter lets any plugin replace or impersonate a gateway. |
| A21 | Moved to RFC 0004 (`GIFT_RECORDED` from `PledgeWriter`). | |
| A22 | Recurring change/pause and biweekly deferred. | The donor cancels and sets up a new gift instead, which avoids per-gateway proration and price-change rules. |
| A23 | Webhook payloads 90 days, encrypted. | Dispute window; payloads hold PII. |
| A24 | Reconcile in `runTimerJobs()` plus a bounded post-webhook step. | Rare staff logins still catch up. |
| A25 | Plugin-local `vendor/` (D10); PayPal and BTCPay over REST. | No gateway SDK in core. |
| A26 | Overridable pages (D8); server-built redirect; host allow-list on server and client; in LIVE mode, server and client require HTTPS for the BTCPay `checkoutLink`, and the API client never forwards `apiKey` across an HTTPS-to-HTTP redirect; readiness warns about plugin head scripts. | Blocks a rewritten or downgraded redirect URL. |
| A27 | One `RecurrenceScheduler` for anchor, first-charge date and UI sentence (D6). | UI and gateway always agree. |
| A28 | Removed 2026-10-05: no newsletter sign-up (D4). | |
| A29 | Stable `sGivingInstallId` UUID in metadata; a cloned DB forces test mode. | A URL hash would orphan subscriptions after a domain change. |
| A30 | Public page `/portal/give` with anonymous-safe Twig and a signed return token. | Themes reused; no session needed after checkout. |
| A31 | Moved to RFC 0004 (attribution changes only through the audited service). This RFC adds: recurring charges use the owner's family at charge time (D11). | History never shifts silently. |
| A32 | Moved to RFC 0004 (one `GivingStatementService`). This RFC reuses it for portal downloads (GIV-30) and adds the non-cash section (GIV-24). | |
| A40 | `plg_PledgeID` (NULL) on `Payment` rows links a payment to an RFC 0004 pledge row of the same fund. `gint_plg_ID` and `grec_plg_ID` carry the link from Pay my pledge to F0, which writes it while the pledge is not ended and is in the charge's fiscal year, else NULL. A linked payment counts toward that pledge only, overriding RFC 0004's matching rule (D21). A pledge with linked payments can be ended, not deleted: the portal Delete and an office edit that drops that fund answer 409. | Pay my pledge fulfils the pledge the member chose, even where the matching rule would pick another. RFC 0004 keeps pledge row ids stable (A34). |

---

## 2. Architecture

### 2.1 Layers

```
Portal /portal/giving/give|history* Public /portal/give*   Finance /finance/online-giving   Admin /admin/online-giving
/api/portal/giving/*               /api/public/giving/*   /api/giving/* (FinanceRoleAuthMiddleware)
                 └──────────────────────┬──────────────────────┘
 CORE ChurchCRM\Giving (no gateway SDKs)
   GivingIntentService ─► gateway->beginCheckout()/beginRecurring() ─► redirect URL (host allow-listed)
   WebhookProcessor ─► verify ─► gwhe ─► re-fetch ─► GivingRecorder (ONLY writer of gateway rows)
   GivingRecorder ─► PledgeWriter ─► pledge_plg · GivingDepositService · GivingNotificationService ─► gntf
   RecurringGiftService · RecurrenceScheduler · RefundService · GivingReconcileService
   GivingScope (D3) · GivingHistoryService · GivingStatementService (from Reports/TaxReport.php)
   FeeCalculator · AllocationSplitter · FinanceAuditService · RateLimitService · ClientIp
 CORE ChurchCRM\Giving\Gateway: PaymentGatewayInterface, GatewayCapabilities, DTOs, PaymentGatewayRegistry
        ▲ register($this, $gateway) in each plugin's boot()
 PLUGINS src/plugins/core/{stripe,paypal,btcpay}/: routes/routes.php (admin tools), routes/webhook.php (§2.5)
```
\* Portal routes are tabs on RFC 0004's Giving page (GIV-83).

`PledgeWriter`, `GivingScope`, `GivingStatementService` and `FinanceAuditService` come from RFC 0004 (GIV-03, GIV-04, GIV-07). `PledgeWriter` is the only writer of pledge rows, for office entry and gateways alike, and runs without a session; `GivingRecorder` calls it.

**Why the contract is in core (revising #8178's adapter plugin):** plugins are discovered core-first then community, unsorted within each directory (`PluginManager::discoverPlugins()`, one `DirectoryIterator` per directory), booted in that order (`PluginManager::loadActivePlugins()`), and autoloadable only once loaded (`PluginManager::registerPluginAutoloader()`), so a gateway could boot before a plugin holding the interface. The callers are core. Precedent: `SystemCalendar` + `SYSTEM_CALENDARS_REGISTER` + `HolidaysPlugin` (`Hooks::SYSTEM_CALENDARS_REGISTER`, applied in `SystemCalendars::getCalendars()`, filled in `HolidaysPlugin::boot()`), with an explicit registry call instead of a filter (A20).

**Why the Giving domain is core, not a plugin (revising #8180):** much of the work needs no gateway at all, which is why per-person attribution (#9313), statements, the audit log and the admin editor are their own RFC (0004). The flow writes core tables (`pledge_plg`, `deposit_dep`) inside core transactions, and the portal and finance pages that call it are core. #8180's on/off property is kept: nothing online shows unless `bEnableOnlineGiving` is on and a gateway plugin is active.

**Gateways are plugins, not `Include/modules/gateways/`.** Each gateway is a plugin: the three in `src/plugins/core/`, others as community plugins in `src/plugins/community/`. The Member Portal design first proposed in #9862 reserved `Include/modules/gateways/` for them. The current Member Portal design on master (`.agents/skills/churchcrm/member-portal-design.md`, P14) no longer reserves that folder, so this RFC changes nothing there; RFC 0004's GIV-83 rewrites P14 when the Giving page lands.

### 2.2 Contract (`ChurchCRM\Giving\Gateway`)

```php
interface PaymentGatewayInterface
{
    public function getGatewayId(): string;            // == owning plugin id, [a-z0-9-]{2,20}
    public function getDisplayName(): string;          // donor-facing, translated
    public function getMode(): GatewayMode;            // TEST | LIVE
    public function getCapabilities(): GatewayCapabilities;
    public function getReadiness(): GatewayReadiness;  // keys vs mode, account currency, last webhook
    public function acceptsNewGifts(): bool;           // false = receive-only
    public function getRedirectHosts(): array;         // e.g. ['checkout.stripe.com']

    public function estimateFee(Money $net, PaymentMethodKind $m, bool $recurring): FeeEstimate;
    public function beginCheckout(CheckoutRequest $r): CheckoutSession;     // idempotency root = intent UUID
    public function beginRecurring(RecurringRequest $r): CheckoutSession;
    /** Top-level GET return; PayPal captures here; idempotent. @return GatewayEvent[] */
    public function completeReturn(string $checkoutRef): array;
    /** Best effort; Stripe expires the Checkout session, others no-op */
    public function expireCheckout(string $checkoutRef): void;
    public function fetchPayment(string $paymentRef): PaymentSnapshot;      // authoritative

    public function getRecurring(string $subscriptionRef): RecurringSnapshot;
    /** "already cancelled" and "not found" are success */
    public function cancelRecurring(string $subscriptionRef, string $idempotencyKey): RecurringSnapshot;
    public function createManageUrl(string $customerRef, string $returnUrl): ?string;  // Stripe billing portal
    public function refund(string $paymentRef, Money $amount, string $idempotencyKey): RefundResult;

    public function verifyWebhook(ServerRequestInterface $req): VerifiedWebhook;  // raw-body signature; throws
    /** Re-fetches referenced objects. @return GatewayEvent[] */
    public function normalizeWebhook(VerifiedWebhook $hook): array;
    /** Missed-webhook catch-up. @return iterable<GatewayEvent> */
    public function eventsSince(\DateTimeImmutable $since, int $maxApiCalls): iterable;
    /** @return iterable<PayoutLine> when payout === Itemized */
    public function listPayoutLines(string $payoutRef): iterable;
    /** Embedded/modal only (GIV-60): SDK URLs, CSP sources, publishable config */
    public function getClientAssets(string $page): ClientAssets;
}
```

**`GatewayCapabilities`** (read-only; helpers `supportsRecurring()`, `offersFeeCoverage()`)

| Capability | Values |
|---|---|
| `recurring` | None, Automatic, Reminder (reserved) |
| `frequencies` | subset of weekly, monthly, annually |
| `donorPicksDay`, `monthEnd` | bool; `monthEnd` = can bill on the last day of the month |
| `methods` | card, ach, apple_pay, google_pay, paypal, btc_onchain, btc_lightning |
| `applePay`, `googlePay`, `walletRecurring` | bool |
| `refunds`, `partialRefunds` | Api, ClaimLink, None; bool |
| `asyncSettlement`, `nonCash` | bool |
| `checkoutModes` | Redirect, Embedded, Modal |
| `customerPortal` | bool |
| `payout` | Itemized, Batch |
| `currencies`, `minimumAmount` | ISO codes; Money |
| `feeModels` | per method: `percentBps`, `fixedCents`, `capCents?`, `recurringSurchargeBps` |

**`CheckoutRequest`:** `intentUuid`, `channel`, `net`, `coveredFee`, `gross`, `currency`, `methodKind`, `description`, `returnUrl`, `cancelUrl`, `customerRef?`, `prefillEmail?`, `locale`, metadata `{churchcrm_intent, churchcrm_install}` (never personal data). **`RecurringRequest`** adds `frequency`, `dayOfMonth|weekday|monthOfYear`, `firstChargeDate`, `anchor`, `grecUuid`.

**`GatewayEvent`:** Payment{Processing, Succeeded, Failed, Expired, NeedsReview}, Refunded, Dispute{Opened, FundsWithdrawn, FundsReinstated}, Recurring{Activated, Charged, ChargeFailed, Updated, Cancelled}, PayoutReconciled, Ignored.

### 2.3 Capability matrix (v1)

| | Stripe | PayPal | BTCPay Server |
|---|---|---|---|
| recurring | Automatic (Subscriptions) | Automatic (Subscriptions v1, D7) | None; BTCPay 2.3.0 Subscriptions are donor-initiated (reminders or prepaid credit), reserved as Reminder |
| frequencies / monthEnd | W/M/A; yes | W/M/A via `start_time`; off until proven (Q9) | – |
| methods | card (+ Apple/Google Pay on hosted page), ACH (optional, off) | PayPal account or guest card on PayPal's page | on-chain; Lightning optional |
| walletRecurring | yes | – | – |
| refunds | Api, partial | Api, partial | ClaimLink |
| asyncSettlement / nonCash | ACH / no | **yes** (PENDING captures) / no | yes / **yes** |
| payout | Itemized | Batch | Batch |
| default fees | 2.9% + 0.30; recurring +0.7% (F9); ACH 0.8%, cap $5 | 3.49% + 0.49 | zero, no prompt |
| library | `stripe/stripe-php` v21 in plugin `vendor/` (MIT; needs only PHP ≥ 7.2 + curl, json, mbstring); API version pinned | Guzzle REST. `paypal/paypal-server-sdk` 2.4.0 covers Orders, Payments and Subscriptions v1 but adds three `apimatic/*` packages and a custom non-MIT license | ~150-line Greenfield client; the official one's repo has no LICENSE |

### 2.4 Registration, release gate, hooks

- Each gateway plugin's `boot()` calls `PaymentGatewayRegistry::register($this, $this->gateway())`. `boot()` runs before `PluginManager` stores the instance (`PluginManager::loadPlugin()`), so `register()` only records the entry. `forGiving()` and `get()` verify entries lazily, after loading ends, and drop (logged, red on readiness):
  - an owner that is not the loaded instance (`PluginManager::getPlugin()`);
  - `getGatewayId() !== $owner->getId()`;
  - a class outside the owner's `mainClass` namespace;
  - an owner neither core nor approved with `payments.process` (new in `ApprovedPluginRegistry::KNOWN_PERMISSIONS`), except `fake-gateway` under `CHURCHCRM_GIVING_SANDBOX=1`;
  - a taken id.
- **"Core" is decided by path:** the realpath of `PluginMetadata::getPath()` must lie under `src/plugins/core/`. The `plugin.json` `type` is self-declared (read in `PluginMetadata::__construct()`) and trusted by `PluginManager::getVerificationStatus()`; only URL installs check it (`PluginInstaller::extractAndValidate()`); `AbstractPlugin::getType()` returns 'community' for every plugin. `discoverPlugins()` scans community after core and keys by id, so a community plugin with id `stripe` would replace core Stripe; GIV-10 makes `discoverPlugins()` and `PluginInstaller` refuse a community id equal to a core id.
- Entries are bound to owners, so load order is irrelevant.
- **`FakeGateway` in CI:** `discoverPlugins()` scans only `src/plugins/core` and `src/plugins/community`, so CI setup copies `cypress/fixtures/plugins/fake-gateway/` into `src/plugins/community/fake-gateway/` (git-ignored) and the test seed activates it.
- `forGiving()` returns gateways that are ready, accept new gifts and support `sGivingCurrency`, ordered by `sGivingGatewayOrder`. Calls into each gateway are wrapped in try/catch, so a broken one drops out; the registry does not rely on `HookManager::applyFilters()` swallowing errors.
- **Release gate (D1):** `GivingFeature::isAvailable()` hides every online-giving surface until GIV-20–24 are merged (the last gateway PR flips it; the sandbox flag bypasses it in CI).

| Hook (`src/ChurchCRM/Plugin/Hooks.php`) | Value | Fires |
|---|---|---|
| `GIFT_RECORDED` | `giving.gift.recorded` | once per new payment group from `PledgeWriter`, after commit |
| `GIFT_REFUNDED` | `giving.gift.refunded` | refund recorded |
| `RECURRING_GIFT_CREATED`/`_CANCELLED`/`_FAILED` | `giving.recurring.*` | lifecycle |
| `DEPOSIT_CLOSED` | `deposit.closed` | wired into `POST /api/deposits/{id}` on 0→1; today only in dead `FinancialService::setDeposit()` |
| `DONATION_RECEIVED` | `donation.received` | unchanged: per row, again on edits (`FinancialService::insertPledgeorPayment()`) |

Payloads never carry secrets. Listeners receiving donor data need `hooks.financial`.

### 2.5 Webhook route in the plugin's folder

**Why a change is needed**
- All `/plugins` routes run behind `AuthMiddleware`, added globally in `src/plugins/index.php`; an unmatched route answers 302 (its `HttpNotFoundException` handler).
- `src/plugins/index.php` first requires `Include/LoadConfigs.php`, which calls `Bootstrapper::init()`. `init()` may redirect via `SystemURLs::checkAllowedURL()` under `bLockURL`, always starts a session with private cache headers (`Bootstrapper::initSession()`), and **auto-upgrades the DB when code is newer** (when `isDBCurrent()` is false).
- `.agents/skills/churchcrm/plugin-development.md` (What a Plugin Can and Cannot Do → Forbidden) forbids unauthenticated write routes.

**Changes (GIV-12)**
1. `plugin.json` key `"webhookRoutesFile": "routes/webhook.php"`, parsed by `PluginMetadata`.
2. The first lines of `src/plugins/index.php` match `/plugins/([a-z0-9-]{2,20})/webhook$`; on a match they select a `Bootstrapper` **webhook boot mode**, require `LoadConfigs.php`, run `PluginWebhookApp`, return.
3. Webhook boot mode skips the `checkAllowedURL()` redirect, `initSession()`, `configureUserEnvironment()` and the auto-upgrade. While `Bootstrapper::isDBCurrent()` is false the app answers **503**; the next normal page load upgrades.
4. `PluginWebhookApp`: no Auth/CSRF/Cors/ChurchInfo/Version middleware, session or cookies; `Cache-Control: no-store`; POST only (405), body ≤ 1 MB (413), high per-IP ceiling; generic JSON errors, never a 302. It loads `webhookRoutesFile` only for an active, registered gateway, giving it a pre-guarded `$webhook` group that may register only `POST ''` (stock: `$webhook->post('', fn($q,$s) => (new WebhookProcessor())->handle($q,$s,$gateway))`); a `routesFile` route at `/{id}/webhook` is unreachable. Logs gateway, event id and outcome, never the body.
5. Body parsing is not a reason for the separate app: Slim's `BodyParsingMiddleware` adds a parsed copy and `Stream::__toString()` rewinds, so raw-body HMAC works either way.
6. Unloaded plugin that still owns `giving_*` rows → **503** + admin alert (gateways retry ~3 days); else JSON 404.
7. Lifecycle guard: optional `AbstractPlugin::canDeactivate(): ?string`, checked by `PluginManager::disablePlugin()` and uninstall. Gateways refuse while `grec_Status IN ('pending','active','past_due','review')` (pending gifts expire within 24 h, F14); the UI offers "Switch to receive-only" or "Cancel all N at the gateway and email donors" (audited, TOTP).
8. Webhooks never reach `AuthMiddleware`, so its public-path exemption (`AuthMiddleware::isPublicPath()`: `/api/public` and `/portal/theme`) is not widened by GIV-12. GIV-33 later adds exactly the `/portal/give` segment (F2).
9. That `plugin-development.md` rule changes to: unauthenticated POST only through `webhookRoutesFile` on a registered gateway, with raw-body signature verification and re-fetch.
10. If you would rather not allow public routes in plugin folders, the fallback is a core `POST /api/public/giving/webhook/{gatewayId}` that calls the same processor.

**`WebhookProcessor::handle()`**

| Step | Case | Response | `gwhe_Status` |
|---|---|---|---|
| 1 `verifyWebhook()` | signature fails | 400 | nothing stored |
| 2 `INSERT gwhe` (UQ gateway + event id) | duplicate already processed, ignored or held | 200 | unchanged |
| | duplicate `failed` or `received` | reprocess | |
| 3 `normalizeWebhook()` | re-fetches the referenced objects | | |
| 4 after the re-fetch | install identity not yet confirmed (A29) | 200 | `held`; processed after "Moved this install", ignored after "This is a copy" |
| | livemode ≠ configured mode, or `churchcrm_install` present and not ours | 200 | `ignored` |
| | no metadata (disputes, dashboard refunds, payouts): resolved through the parent charge, capture or payout to a local `gtxn`/`grec` | | |
| | no local match and the parent carries no `churchcrm_install` (another app on a shared account is normal) | 200 | `ignored` |
| | no local match yet, but the parent carries our `churchcrm_install`: its charge is still processing, held or in review, and the refund or dispute arrived first | 200 | `held` (awaiting parent), re-applied when F0 records the parent (step 8) and by F14 |
| 5 `GivingRecorder::apply()`, one transaction per event | recorded | 200 | `processed` |
| 6 | permanent problem (unknown intent with our install id, amount mismatch, crypto edge cases) | 200 | `review` |
| | transient problem (timeout, deadlock, SQLSTATE 42S02) | 503 | `failed` |

- **Telemetry:** the app logger sends Warning and above to PostHog (`PostHogLogHandler`, pushed in `LoggerUtils::getAppLogger()`) with a synchronous 1 s POST (`TelemetryService::capture()`), and core code on the webhook path logs there (for example a failing hook listener in `HookManager::applyFilters()`). Webhook boot mode builds the app logger without `PostHogLogHandler`, so no telemetry call runs inside the 10 s budget. The `giving` channel never goes to PostHog.
- Stripe endpoints omit `invoice.created` (a failing subscriber delays renewals up to 72 h).

### 2.6 Plugin layout and packaging

```
src/plugins/core/stripe/
  plugin.json  id "stripe", type core, minimumCRMVersion <next>, mainClass ChurchCRM\Plugins\Stripe\StripePlugin,
               routesFile routes/routes.php, webhookRoutesFile routes/webhook.php, hasTest true,
               permissions network.outbound network.inbound secrets.store payments.process,
               settings: mode publishableKey secretKey(secret, rk_) webhookSecret(+Previous, secret) acceptNewGifts
                 enableAch cardFee% cardFeeFixed billingFee% achFee% achFeeCap depositMode(batch|payout)
  composer.json, composer.lock   stripe/stripe-php only; vendor/ built at release, git-ignored
  help.json    webhook URL SystemURLs::getURL().'/plugins/stripe/webhook', restricted-key permissions, test mode
  src/StripePlugin.php (canDeactivate, testWithSettings) · src/StripeGateway.php
  routes/routes.php  admin: Create webhook endpoint, Test connection, Register this domain (GIV-60)
  routes/webhook.php public POST only
```
- PayPal: `mode`, `clientId`, `clientSecret` (secret), `webhookId`, fees. BTCPay: `serverUrl`, `storeId`, `apiKey` (secret, least privilege), `webhookSecret` (secret), `speedPolicy`, `enableLightning`. Neither has a `composer.json`.
- Caches: `plugin.stripe.productId_{mode}`, `plugin.paypal.plan.{mode}.{freq}.{ccy}`.
- Stored keys are `plugin.{id}.{key}` (`PluginManager::getPluginConfigKeyMap()`, `updatePluginSetting()`) in `config_cfg.cfg_name varchar(50)`: the whole string must be ≤ 50 characters (`plugin.paypal.plan.live.annually.USD` is 36).
- **Plugin-local vendor (GIV-09):** `PluginManager::loadPlugin()` requires `{plugin}/vendor/autoload.php` if present, before the PSR-4 autoloader (`registerPluginAutoloader()`). The `composer:install` script in `package.json` also installs each `src/plugins/core/*/composer.json` (`--no-dev`), and `build:signatures` moves after it (`build` runs PHP and frontend in parallel today) so `signatures.json` covers plugin vendor. CI fails if a plugin lock duplicates a core or other-plugin package. One Dependabot `composer` entry per plugin. New `src/plugins/core/.htaccess` mirrors `community/.htaccess` (deny direct PHP); Caddy already blocks both (the `@pluginPhp` matcher in `docker/examples/frankenphp/Caddyfile`).

---

## 3. Data model

**Migrations:** `src/mysql/upgrade/<next>-giving-*.sql` and `<next>-system-ratelimit.sql`, named for the release a maintainer schedules them into (question 4); mirrored by hand in `Install.sql`, `orm/schema.xml`, `cypress/data/seed.sql`. Registered in `upgrade.json` only then. RFC 0004's migrations (anonymous normalize, person attribution, pledge end date, fund flag, finance audit) run first. No column-level `IF NOT EXISTS`.

### 3.1 Changes to existing tables

| Table | Change | Migration / issue |
|---|---|---|
| `pledge_plg` | Moved to RFC 0004: anonymous = both ids NULL (GIV-02); `plg_PerID` (GIV-05); `plg_EndDate` (GIV-82) | |
| `pledge_plg` | `plg_gtxn_ID int NULL` + index; `plg_method` + 'CRYPTO'; `plg_schedule` + 'Annually' | `<next>-giving-columns.sql` / GIV-14 |
| `pledge_plg` | `plg_PledgeID mediumint(9) NULL` + index: on a `Payment` row, the RFC 0004 pledge row it fulfils (A40, D22); no DB FK, set NULL in code if that pledge is deleted | `<next>-giving-columns.sql` / GIV-14 |
| `pledge_plg` | `plg_fr_ID mediumint(9) unsigned NULL` + index (fundraiser); `plg_pn_ID mediumint(9) unsigned NULL` + index (paddle, `paddlenum_pn.pn_ID`) | `<next>-giving-columns.sql` / GIV-14 (the recorder writes them; GIV-50 uses them) |
| `pledge_plg` | `plg_FeeCovered decimal(8,2) NOT NULL DEFAULT 0`: the row's share of a fee the donor chose to cover (A12), included in `plg_amount` | `<next>-giving-columns.sql` / GIV-14 |
| `pledge_plg`, `deposit_dep` | utf8 → utf8mb4 (both are `CHARACTER SET utf8` in `Install.sql`), as 7.7.0 did for the events tables | `<next>-giving-utf8mb4.sql` / GIV-14 |
| `deposit_dep` | `dep_Type` + 'Online'; `dep_Gateway varchar(32) NULL`; `dep_PayoutRef varchar(191) NULL`, UNIQUE(gateway, ref); `dep_FeeAmount decimal(10,2) DEFAULT 0`; `dep_NetAmount decimal(10,2) NULL`; `dep_ReconStatus enum('none','balanced','discrepancy')`; INDEX(gateway, closed, date) | GIV-14 |
| `donationfund_fun` | Moved to RFC 0004: `fun_OnlineGiving` (D14, GIV-80). The Give tab offers the same funds. | |

**Propel.** RFC 0004 (GIV-05) names the two FKs from `pledge_plg` to `person_per`. `plg_PledgeID` is a self-reference; GIV-14 names it `phpName="FulfilledPledge"`, `refPhpName="FulfillingPayment"`.

**Mapping:** card, Apple Pay, Google Pay, PayPal → CREDITCARD; ACH → BANKDRAFT; bitcoin → CRYPTO + `gnc`. All → `dep_Type` 'Online' with `dep_Gateway`. The label ("Visa •••• 4242") lives on `gtxn`.

### 3.2 New tables
utf8mb4 InnoDB, `int(11)` ids, real FKs between new tables; `person_per`/`family_fam` references are `mediumint(9) unsigned NULL` with no DB FK: person delete clears them in code (§6), family delete keeps them (§5.4); money never cascades.

**`giving_intent_gint`** (one checkout attempt)

| Column | Type / notes |
|---|---|
| `gint_ID`, `gint_Uuid` | int PK; char(36) UQ, sent as Stripe `churchcrm_intent`, PayPal `custom_id`, BTCPay `orderId`; idempotency root |
| `gint_ClientKey` | varchar(64) NULL UQ; browser `Idempotency-Key` |
| `gint_Gateway`, `gint_Mode`, `gint_Channel`, `gint_Kind` | varchar(32); enum('test','live'); enum('portal','public','fundraiser','admin'); enum('one_time','recurring') |
| `gint_per_ID`, `gint_fam_ID`, `gint_fr_ID`, `gint_pn_ID`, `gint_AttributeTo` | NULL, from session or verified token only; enum('person','family','anonymous') |
| `gint_plg_ID` | mediumint(9) NULL; the pledge row from Pay my pledge (A40), checked against the actor's scope |
| `gint_Method`, `gint_Currency`, `gint_Net`, `gint_CoveredFee`, `gint_Gross` | varchar(20), char(3), decimal(10,2); gross ≤ 999,999.99 |
| `gint_FeeModel`, `gint_Allocation` | snapshot (`290bps+30`); immutable JSON `[{fundId,amount}]` |
| `gint_grec_ID`, `gint_CheckoutRef` | FK NULL; cs_/order/invoice id |
| `gint_DonorEmail`, `gint_DonorName` | public page only; erasable |
| `gint_Status` | enum('created','redirected','processing','completed','failed','abandoned','expired','review'); `processing` = the gateway reported a payment still settling (ACH, PayPal PENDING, on-chain), set by `completeReturn()` or a webhook |
| `gint_ClientIpHash`, `gint_Created`, `gint_Updated`, `gint_Expires` | HMAC, purged at 30 days; datetimes |

**`giving_transaction_gtxn`** (ledger)

| Column | Type / notes |
|---|---|
| `gtxn_ID`, `gtxn_Uuid`, `gtxn_Gateway`, `gtxn_Mode` | admin URLs use the UUID |
| `gtxn_Kind`, `gtxn_Parent_ID` | enum('charge','refund','dispute_debit','dispute_credit'); self-FK to the charge |
| `gtxn_ExternalRef` | PI or subscription invoice id; PayPal capture/sale id; BTCPay invoice id. **UQ(gateway, mode, kind, ref)** is the exactly-once anchor |
| `gtxn_gint_ID`, `gtxn_grec_ID`, `gtxn_per_ID`, `gtxn_fam_ID`, `gtxn_Channel` | attribution snapshot; gint channels + 'recurring' |
| `gtxn_Status`, `gtxn_ReviewReason` | enum('pending','succeeded','failed','review','refunded','partially_refunded','disputed'), failed → succeeded allowed; amount_mismatch, unknown_intent, crypto_partial/late/over/marked |
| `gtxn_Currency`, `gtxn_Gross` | decimal(10,2) signed; negative for refund and debit |
| `gtxn_CoveredFee`, `gtxn_FeeEstimate`, `gtxn_Fee`, `gtxn_BillingFeeEst`, `gtxn_Net`, `gtxn_RefundedAmount` | `gtxn_Fee` = processing fee (NULL on Stripe standalone-fee accounts); `gtxn_BillingFeeEst` = Stripe Billing estimate |
| `gtxn_Method`, `gtxn_MethodLabel`, `gtxn_Wallet` | brand and last 4 only |
| `gtxn_OccurredAt`, `gtxn_SettledAt` | UTC → `sTimeZone`; drives `plg_date` and FY |
| `gtxn_GroupKey` (UQ), `gtxn_dep_ID`, `gtxn_PayoutRef`, `gtxn_ReceiptNo` (`YYYY-{id}`), `gtxn_Detail` (JSON: claim link, dispute id) | |
| `gtxn_InitiatedBy`, `gtxn_Reason`, `gtxn_Created`, `gtxn_Updated` | signed mediumint (−3 allowed) |

**`giving_recurring_grec`** (one recurring gift)

| Column | Type / notes |
|---|---|
| `grec_ID`, `grec_Uuid` | int PK; char(36) UQ, used in portal URLs |
| `grec_Gateway`, `grec_Mode`, `grec_SubscriptionRef` | UQ(gateway, mode, ref); ref NULL until the gateway creates the subscription |
| `grec_gcus_ID` | FK NULL |
| `grec_per_ID`, `grec_fam_ID`, `grec_AttributeTo` | owner: set at creation, NULL once the owner is deleted (§6); enum('person','family') |
| `grec_plg_ID` | mediumint(9) NULL; the pledge a Pay my pledge recurring gift was set up for (A40). Never changes the pledge |
| `grec_Currency`, `grec_Net`, `grec_CoveredFee`, `grec_Gross`, `grec_Allocation` | decimal(10,2); JSON `[{fundId,amount}]` |
| `grec_Frequency`, `grec_DayOfMonth`, `grec_Weekday`, `grec_MonthOfYear`, `grec_FirstChargeDate` | enum('weekly','monthly','annually') DEFAULT 'monthly'; 1–28, 31 = last day |
| `grec_Status` | enum('pending','active','past_due','cancelled','expired','review'); exits in F3.5–3.6, F5, F7, F14 |
| `grec_MethodLabel`, `grec_LastChargedAt`, `grec_LastChargedAmount`, `grec_NextChargeAt`, `grec_LastSyncedAt`, `grec_FailureCount` | |
| `grec_Created`, `grec_CreatedBy`, `grec_CancelRequestedAt`, `grec_CancelRequestedBy`, `grec_CancelledAt`, `grec_CancelledBy` | signed mediumint for actor ids |
| `grec_CancelSource` | enum('donor','staff','gateway','plugin_disable') |

**Others**
- `giving_customer_gcus`: gateway, mode, customer ref, `gcus_per_ID` (NULL once the person is deleted); UQ(gateway, mode, ref) and UQ(gateway, mode, per_ID).
- `giving_webhook_event_gwhe`: `gwhe_ID bigint`, gateway, mode, `gwhe_EventRef` (UQ with gateway), `gwhe_Source` (webhook, reconcile, return), type, object ref, `gwhe_Status` (received, held, processed, ignored, failed, review), attempts, sanitised last error, payload hash, `gwhe_Payload` (encrypted, 90 days), timestamps.
- `giving_notification_gntf` (outbox): `gntf_Type` (receipt, refund, recurring_created/cancelled/failed, fundraiser_receipt, admin_alert), `gntf_DedupeKey varchar(190) UQ` (`receipt:{gtxn}`), to-email, per/fam/gtxn/grec ids, scheduled-for, `gntf_Status` (pending, sent, failed, skipped), attempts, last error, sent-at. Standalone (Volunteer v2's `volunteer_notification_vntf` is similar).
- `giving_noncash_gnc` (1:1 with a crypto charge `gtxn`, shared by all its rows): `gnc_gtxn_ID` PK/FK, `gnc_Asset`, `gnc_Quantity decimal(24,12)`, `gnc_Network enum('onchain','lightning')`, `gnc_Rate decimal(20,8)`, `gnc_RateSource`, `gnc_TxIds`, `gnc_AppraisalFlag` (> $5,000, Form 8283 Section B), `gnc_DisposedDate`, `gnc_DisposalProceeds`, `gnc_Form8282FiledDate`.
- `finance_audit_faud`: moved to RFC 0004 (GIV-03). GIV-13 adds `faud_IpHash` (HMAC, keyed outside the DB, §3.4).
- `system_ratelimit_srl`: `srl_Key varchar(191) PK` (hashed bucket), window start, count; one atomic UPDATE per hit.

### 3.3 Invariants (enforced in code; a nightly check of I1–I4, I7–I11, I14 and RFC 0004's I6, I12 and I13 raises `admin_alert`)
- **I1** Σ `plg_amount` for a succeeded charge's rows = `gtxn_Gross`.
- **I2** Rows are written once, in the transaction that marks the charge succeeded, under `SELECT … FOR UPDATE`, on the Propel write connection only.
- **I3** Σ refunds and dispute debits ≤ gross + dispute credits.
- **I4** Gateway rows never change amount, date, method or fund split and are never deleted, except by the test purge (F13). Only two updates are allowed: `plg_FamID`/`plg_PerID` through the attribution service (F8), and one `plg_depID` NULL → payout deposit attach by `GivingDepositService` (F13); both audited, in place.
- **I5** Moved to RFC 0004 (the family is set at write time through `PledgeWriter::familyOf()`). Gateway rows follow it.
- **I6** Moved to RFC 0004 (anonymous = both ids NULL; `plg_FamID` is never 0).
- **I7** A payout deposit is `balanced` when Σ rows − `dep_FeeAmount` = `dep_NetAmount`, with `dep_FeeAmount` = every fee balance transaction in the payout, including Stripe's separate `stripe_fee` (Billing) lines.
- **I8** Recurring gifts are cancelled locally only after the gateway confirms, or says already cancelled or not found.
- **I9** No test-mode rows in live books after go-live.
- **I10** Every CRYPTO row's `plg_gtxn_ID` (for a refund, its `gtxn_Parent_ID`) has a `gnc` row; cash totals exclude CRYPTO.
- **I11** Report totals = ledger totals, refunds included.
- **I14** A `plg_PledgeID` names a `Pledge` row of the same fund, and only `Payment` rows carry one.

### 3.4 Settings (category-less core `ConfigItem`s on `/admin/online-giving`)

| Key | Default | Meaning |
|---|---|---|
| `bEnableOnlineGiving`, `bEnablePublicGiving` | 0, 0 | master (plus D1 gate); public page. The portal Giving page itself is RFC 0004's `bPortalShowGiving` |
| `sGivingCurrency` | USD | ISO 4217, 2-decimal; replaces hard-coded `<CURDEF>USD` in OFX (`Deposit::getOFX()`) |
| `sGivingGatewayOrder`, `sGivingDepositBatching`, `aGivingAmountPresets` | stripe,paypal,btcpay; weekly; 25,50,100,250 | batching: daily, weekly, monthly |
| `iGivingMinPublic`, `iGivingMaxPublic`, `iGivingMinMember`, `iGivingMaxMember` | 5, 10,000; 1, 25,000 | whole currency units; hard cap 999,999.99 |
| `sGivingCoverFeeDefault`, `iGivingFeeCoverFund` | unchecked, 0 | 0 = spread |
| `sGivingReceiptText`, `bGivingReceiptPerRecurringCharge` | IRS wording; 1 | `bFinanceShowGivenBy` and `sGivingStatementMode` are RFC 0004's |
| `sGivingTrustedProxies` | empty | CIDRs for `ClientIp` |
| `sGivingPrivacyNoticeUrl`, `sGivingRefundPolicyUrl` | empty | shown on giving pages (GIV-71) |
| `iGivingRefundAlertAmount`, `iGivingWebhookRetentionDays`, `iGivingPublicCircuitBreaker` | 500, 90, 20 | alert threshold; days; failed/abandoned public intents per hour (`processing` ones do not count) |
| `sGivingInstallId` | set on first save | install UUID (A29) |

The HMAC key for IP hashes and return tokens is not a setting. It is derived from the secrets key outside the DB (A15): `hash_hkdf('sha256', <secrets key>, 32, 'giving-hmac')` (GIV-13). A key stored in `config_cfg` would sit in every dump, and IPv4 HMACs can be brute-forced (2^32) with it. Without a secrets key giving runs in test mode only, with a random test key kept in the DB and dropped at go-live. Rotating the secrets key invalidates open return tokens (the page then shows "Thank you").

---

## 4. Flows

**F0. Recording (`GivingRecorder::recordCharge()`).** Every path ends here, on `Propel::getWriteConnection()` only; nothing uses the mysqli connection behind `FunctionsUtils::genGroupKey()`/`runQuery()`.
1. `BEGIN`; upsert `gtxn` by (gateway, mode, kind, ref); `SELECT … FOR UPDATE`; already succeeded → commit, return.
2. Resolve the owner by intent UUID or subscription ref. Stripe sessions set `payment_intent_data.metadata`, `subscription_data.metadata` and `client_reference_id`, so PaymentIntent, invoice and subscription objects carry the ids.
3. Check the `fetchPayment()` snapshot: succeeded, same currency, amount = `gint_Gross`/`grec_Gross`; else `review`, no rows. A one-fund recurring charge records the actual amount; several funds are scaled and flagged. A BTCPay overpayment goes to review with no rows (F11).
4. `AllocationSplitter` splits the gross: covered fee in proportion, by largest remainder (A12); each line keeps its fee share for `plg_FeeCovered`.
5. `GivingDepositService::depositFor()` under `GET_LOCK('giving_deposit_'.gw)` on the same connection (batch); payout mode leaves `plg_depID` NULL.
6. `PledgeWriter::insertGroup()`, one Payment row per fund: date = `gtxn_OccurredAt` in `sTimeZone`; FY via `FiscalYearUtils::getFiscalYearIdForDate()`; ids per F8 (RFC 0004; recurring: `familyOf(owner)` at charge time, D11); `plg_PledgeID` per A40 on the rows of the pledge's fund; method per §3.1; `plg_gtxn_ID`, `plg_fr_ID`, `plg_pn_ID` (from `gint_fr_ID`/`gint_pn_ID`); `plg_FeeCovered` = the line's fee share; NonDeductible = fundraiser FMV part or 0; schedule: one-time → 'Once', weekly/monthly/annually → 'Weekly'/'Monthly'/'Annually'; `plg_aut_Cleared=1`; EditedBy = actor or −3; GroupKey `gtxn{id}|0|{fam}|{funds}|{date}` (no scan).
7. Update `gtxn` (succeeded, GroupKey, deposit, fee), `gint` (completed), `grec` (last/next charge; active unless in review); audit; enqueue `receipt:{gtxn}` (an INSERT into `gntf`, status pending); `COMMIT`.
8. After commit: `DONATION_RECEIVED` per row, `GIFT_RECORDED` once; GIV-17 flushes up to 5 notifications; any `gwhe` held awaiting this charge (a refund or dispute that arrived first) is applied now.

**Family lookup.** Every place that copies "the person's family" into `gint_fam_ID`, `gtxn_fam_ID` or `grec_fam_ID`, as well as `plg_FamID`, calls RFC 0004's `PledgeWriter::familyOf(Person)`, which maps `per_fam_ID = 0` to NULL (I6).

**Install identity (A29).** `sGivingInstallId` is created when giving is first configured (so test gifts carry it) and fixed at go-live, with a fingerprint of `$URL` and DB name. When the fingerprint changes, the admin page asks "Moved this install" (keep id) or "This is a copy" (new id, test mode, keys re-entered); live mode is off until answered. Webhooks that arrive meanwhile are verified and stored as `gwhe` `held` (§2.5); "Moved this install" processes them, "This is a copy" marks them ignored. A 200-ignored live event would never be resent.

**F1. Portal gift, one-time**
1. `GET /portal/giving/give` (the Give tab on RFC 0004's Giving page) with the actor that `PortalAccessMiddleware` sets (`PortalSelfService::ACTOR_ATTRIBUTE`): funds with `fun_Active='true' AND fun_OnlineGiving=1` in `fun_Order`, `forGiving()` capabilities and fee models, recurring notices, fundraiser balances.
2. Fund lines (up to 5, or presets), gateway and method, optional fee cover; the HoH also sees "Give as: Me / Our household". **Pay my pledge** (D22) arrives as `?pledge={id}`: one fund line locked to the pledge's fund, amount = its amount per gift (editable), and recurring preset to its schedule when the gateway supports it; the pledge must be in the actor's scope (else 404) and is sent on as `gint_plg_ID`.
3. `POST /api/portal/giving/intents` (`Idempotency-Key`; `CSRFMiddleware`, `PortalApiMiddleware`, and the giving no-masquerade middleware for Login as User, §5.1). No person or family ids accepted. The server validates funds and limits, recomputes the fee (> 1¢ difference → 409 "Total changed, please review"), creates `gint` (per = actor, fam = `familyOf(actor)`), calls `beginCheckout()`, checks the URL's host against `getRedirectHosts()`, returns `{redirectUrl}`. For `kind=recurring` it answers **422** when the gateway lacks `supportsRecurring()`, the frequency is not in `frequencies`, or day = last and the gateway lacks `monthEnd`, so a crafted POST cannot create a `grec` the gateway cannot honour.
4. The `portal-giving` bundle re-checks the host, then `location.assign()`. No form post: the CSP's `form-action 'self'` (`Include/Header-Security.php`) covers redirect targets.
5. Return: `GET /portal/giving/return?intent={uuid}` (top-level GET keeps the Lax cookie); `gint_per_ID` must equal the actor (else 404); `completeReturn()` → F0; shows succeeded with receipt, pending (ACH, PayPal PENDING, on-chain; polls `GET /api/portal/giving/intents/{uuid}`), or failed.
6. Webhook before or after the return: F0 makes order irrelevant.

**F2. Public anonymous gift (D4)**
1. **`GET /portal/give[?fund=&amount=]`**, outside `PortalAccessMiddleware`. The AuthMiddleware public-path list gains exactly `/portal/give`, matched on whole segments (`AuthMiddleware::isPublicPath()`), so `/portal/giving` stays private. `PublicGivingEnabledMiddleware` answers 404 when `bEnablePublicGiving` is off, except for a request carrying a valid `fundraiserPayment` token (the page and its `POST /api/public/giving/intents`), so fundraiser pay links (F12) work without a public page; the D1 gate and `bEnableOnlineGiving` still apply.
   - **Anonymous-safe Twig:** with member sign-in, the portal's globals read the portal principal and already render signed out (`/portal/login` uses them). Without it, `PortalExtension::getGlobals()` always builds `member` (`getMember()`), `nav` (`PortalNav::build()`) and color mode (`getColorMode()`), and each calls `AuthenticationManager::getCurrentUser()`, which throws without a session provider (`getAuthenticationProvider()`). GIV-33 adds `AuthenticationManager::tryGetCurrentUser(): ?User` and makes these globals return null or defaults. The route calls `PortalTwig::preparePage()` for the CSP nonce.
   - `giving/public.html.twig` extends `layout-public.html.twig` (logo, no nav or account menu); both overridable (D8). CSRF token from the anonymous session.
2. **Form:** fund, amount, gateway/method, fee cover; optional "Email me a receipt" (prompt at $250+); honeypot, minimum fill time; privacy and refund links; **"If you are a member, click here"** → `/portal/giving/give?fund=&amount=`; "No login yet? Contact the church office"; "Members can sign in to give monthly"; signed-in visitors see "give from your account". The member link and "sign in to give monthly" render only when the portal Give tab is available (portal on, `bEnabledFinance`, `bPortalShowGiving`, the D1 gate, `bEnableOnlineGiving`, a gateway); otherwise the page shows only "Contact the church office", so members never log in to a hidden section.
3. **`POST /api/public/giving/intents`** behind `StrictCSRFMiddleware` (rejects the `X-API-Key` bypass in `CSRFMiddleware::process()`), `RateLimitMiddleware` and the circuit breaker. Creates `gint` (channel public, ids NULL, email). Always rejects `kind=recurring` (422). Stripe gets `customer_email` as prefill only, not `receipt_email`.
4. **Return** carries an HMAC token (uuid + 7-day expiry, giving HMAC key, §3.4): `GET /portal/give/return?t=…` needs no session. Valid → status, amount, fund, printable receipt. Invalid/expired → "Thank you. If you entered an email, your receipt is on its way." Never a 404 after paying.
5. **F0 writes** both ids NULL, EditedBy −3; receipt to the email if given.
6. **Member link:** a signed-out request for a portal page stores its path and query, and `/portal/login` returns there after the member signs in (member sign-in; `PortalSignInPages::landingUrl()` accepts portal pages only). Before member sign-in, `AuthMiddleware::redirectToLogin()` does the same for `AuthenticationManager::authenticate()`, through `validateRedirectPath()`, after any 2FA step. `/portal/giving` must stay behind the global `AuthMiddleware`, which `MvcAppFactory::create()` adds to every MVC app, `src/portal/index.php` included, and which runs before `PortalAccessMiddleware`, whose own redirect to `getSessionBeginURL()` stores no location.
7. Never auto-matched to a person, including through the email log (§6). No recurring on this page.

**F3. Recurring setup** (portal only)
1. The toggle shows only when `supportsRecurring()` (and `walletRecurring` for wallets). BTCPay: "Bitcoin gifts can't repeat automatically."
2. Defaults: Monthly on today's day (29–31 → "Last day"; on a gateway without `monthEnd`, such as PayPal until Q9 is settled, 29–31 → the 1st, so the first gift is on the 1st of next month); Weekly on today's weekday; Annually on today's date. Choices: days 1–28 and "Last day" (if `monthEnd`). `RecurrenceScheduler` applies the same fallback, so the date and the sentence agree.
3. `RecurrenceScheduler::plan()` returns the first-charge date (next occurrence of the chosen day; today only if today is that day), the anchor and the sentence ("First gift on Oct 1, then monthly on the 1st"). Anchors are 12:00 `sTimeZone` in UTC, so DST never moves the date.

   | | Stripe Checkout `mode=subscription` | PayPal Subscriptions v1 |
   |---|---|---|
   | Chosen day is today (except "Last day" on a 28th–30th, next row) | no anchor; charged at checkout | no `start_time` (now) |
   | Monthly 1–28 | `subscription_data.billing_cycle_anchor` = next occurrence, `proration_behavior=none` | `start_time`; interval MONTH |
   | Monthly last day | `subscription_data.billing_cycle_anchor_config.day_of_month=31` (bills on the last day of short months), sent even when today is the last day of a 28–30 day month, so the first gift is next month's last day and the sentence says so: a subscription started with no anchor on Apr 30 would renew on the 30th. On the 31st, no anchor is the same thing and charges at checkout | hidden until verified (Q9) |
   | Weekly | anchor = next chosen weekday | `start_time`; WEEK |
   | Annually | `billing_cycle_anchor_config` month + day | `start_time`; YEAR |

   Stripe rules: the anchor must fall in the first period (the next occurrence always does); anchor, anchor config and trials are mutually exclusive; with `proration_behavior=none` **no invoice is issued before the anchor** and the session completes with `payment_status=no_payment_required`. Defensive rule: `invoice.paid` with `amount_paid=0` is ignored.
4. A `kind=recurring` intent creates `grec` (pending) + `gint`. Stripe: one `price_data` line (gift product, gross, `recurring{interval}`), `subscription_data.metadata`, anchor fields. PayPal: lazy plan per (mode, frequency, currency); `POST /v1/billing/subscriptions` with `plan_id`, inline `plan` amount override, `start_time`, `custom_id=grec_Uuid`; redirect to approve.
5. Activation (`checkout.session.completed`, `customer.subscription.created`, `BILLING.SUBSCRIPTION.ACTIVATED`) → `grec` active with ref, `gcus`, next date; "Recurring gift set up" email. **Money is recorded only from `invoice.paid` or `PAYMENT.SALE.COMPLETED`.**
6. A donor who backs out of checkout leaves a `pending` `grec` with no activation. F14 step 1 expires it with its `gint`; `expireCheckout()` first expires Stripe's Checkout session (`POST /v1/checkout/sessions/{id}/expire`), and an unapproved PayPal subscription cannot charge. An activation that arrives for an expired `grec` goes to review.

**F4. Gateway charges a recurring gift.** `invoice.paid`/`PAYMENT.SALE.COMPLETED` → verify → `gwhe` → re-fetch → `grec` via subscription metadata or `billing_agreement_id` → F0 with ref = invoice/sale id, FamID = `familyOf(owner)` at charge time (D11; a household gift whose owner changed family raises an alert) → `grec` last/next (`current_period_end`/`next_billing_time`), `FailureCount=0`, active (a `grec` in review stays in review) → receipt if `bGivingReceiptPerRecurringCharge`.

**F5. More Info and Cancel**
1. **Notices** at the top of the Give tab: one card per actor-owned `grec` in active, past_due or review: "Monthly gift of $50.00 to General Fund on the 15th · next Oct 15 · [More Info]"; past-due in amber. A `pending` `grec` shows only after the donor has returned from checkout (its `gint` is processing or completed), as "Waiting for {Gateway} confirmation" with no More Info or Cancel. An abandoned checkout shows nothing.
2. **More Info** opens a native `<dialog>` from `GET /api/portal/giving/recurring/{uuid}` (owner only, else 404; refreshed by `getRecurring()` when older than 10 min, else "as of"): set-up date, status, amount, funds and covered fee, frequency and day, last charged (or "Not yet charged"), next charge, method, gateway, subscription id (copyable). Buttons: Update payment method (Stripe portal), **Cancel**, Close.
3. **Cancel** → in-dialog "Are you sure? This stops future gifts of $50.00 monthly. Gifts already made are not refunded." **[Yes] [No]** (messages stay in the dialog; toasts render behind the top layer).
4. **Yes** → `POST /api/portal/giving/recurring/{uuid}/cancel` (CSRF; 409 in masquerade). `RecurringGiftService::cancel()`: (a) check owner and status, set `CancelRequestedAt/By`; (b) call `cancelRecurring(ref, "cancel:{uuid}")` **outside any lock**; (c) on success, "already cancelled" or "not found": lock `grec`, set `Cancelled`, time, actor, `CancelSource=donor`, audit, `RECURRING_GIFT_CANCELLED`, confirmation email; (d) on error: **502** "We couldn't reach {Gateway}; your gift is still active", nothing else changes (I8), attempt audited.
5. `location.reload()` removes the notice.
6. `customer.subscription.deleted`/`BILLING.SUBSCRIPTION.CANCELLED` with a recent `CancelRequestedAt` records source donor; otherwise gateway (dashboard cancel).

**F6. Failures**

| Event | Gateway | Result |
|---|---|---|
| One-time payment fails | all | generic text; `gint` failed; no rows; counts toward limits |
| `invoice.payment_failed` | Stripe | `gtxn` failed (ref = invoice id); `grec` past_due, `FailureCount+1`; `recurring_failed` email; `RECURRING_GIFT_FAILED`; past-due list |
| Successful retry of the same invoice | Stripe | records (`gtxn` failed → succeeded) |
| Retries exhausted → `customer.subscription.deleted` | Stripe | `grec` cancelled, source gateway |
| `BILLING.SUBSCRIPTION.PAYMENT.FAILED` | PayPal | `grec` only: past_due, `FailureCount+1`, email, hook. No `gtxn`: the event carries no sale id, so the ledger key could not be exactly-once |
| `BILLING.SUBSCRIPTION.SUSPENDED` | PayPal | past_due + admin alert |
| Capture PENDING (eCheck, review, receiving preferences) | PayPal | `gint` processing; `PAYMENT.CAPTURE.COMPLETED` records; `PAYMENT.CAPTURE.DECLINED` → failed |
| ACH fails after Processing | Stripe | failed; a later return is a dispute (F7) |

**F7. Refunds and disputes**
- **Start:** Finance user with a fresh TOTP code (A19), full or partial with reason → `POST /api/giving/transactions/{uuid}/refund`; amount ≤ gross − refunded − dispute debits + dispute credits (I3); refused while `gtxn_Status = 'disputed'`; pending refund `gtxn`; `refund(ref, amt, "refund:{id}:{n}")`; no rows yet; above `iGivingRefundAlertAmount` admins are emailed.
- **Record:** `charge.refunded`/`refund.updated`, `PAYMENT.CAPTURE.REFUNDED`, `PAYMENT.SALE.REFUNDED` → **negative** rows in proportion to the charge's rows (its immutable allocation, I4), NonDeductible and `plg_FeeCovered` included; same ids; refund date; open deposit (payout mode: NULL until absorbed); "Refund of {ReceiptNo}"; cross-year → re-issue flag; `GIFT_REFUNDED`; email. Dashboard refunds take this path (audited as external). Money returns only to the original method.
- **BTCPay:** pull payment; claim link stored; staff **Record refund** after payout (only manual money write; audited, TOTP).
- **Disputes:** `charge.dispute.created`/`CUSTOMER.DISPUTE.CREATED` → disputed + alert, `grec` to review · `funds_withdrawn`/`PAYMENT.CAPTURE.REVERSED` → `dispute_debit`, negative rows, fee into `gtxn_Fee` · `funds_reinstated` → `dispute_credit`, positive rows · `closed` → status. A `grec` in review keeps recording charges; staff either **Resume** it (after `getRecurring()` confirms it is live → active) or cancel it (TOTP, → cancelled). Review blocks plugin disable (§2.5 item 7).

**F8. Admin entry.** Moved to RFC 0004 ("Given by", the `PersonID` rule, the attribution service, the family donation move and the D11 move). This RFC adds:
- **Gateway groups** are read-only: `PUT` → 409 "Online gifts can only be re-attributed"; `DELETE` → 409 "Refund it through the gateway".
- The attribution service also updates the group's `gtxn` (`gtxn_per_ID`/`gtxn_fam_ID`), and serves gateway groups through the same `PATCH /api/payments/{groupKey}/attribution`. It leaves `plg_EditedBy` alone, so −3 survives.
- The family donation move is refused while the family owns a pending, active, past_due or review `grec`.

**F9. Fee estimate** (`FeeCalculator`, integer cents, round up)
- `fee(x) = min(cap ?? ∞, ceil(x·p) + f)`. Cover: `G = ceil((N+f)/(1−p))`; `while (G − ceil(G·p) − f < N) G++`; with a cap, `G = min(G, N + cap)`. This is the smallest G with `G − fee(G) ≥ N`. Stripe recurring adds 0.7% to `p`.
- $100: card 2.9% + 0.30 → **$103.30**; Stripe recurring → **$104.05**; ACH → **$100.81**; PayPal → **$104.13**. Above the cap: ACH $1,000 → **$1,005.00** (the $5 cap applies to gifts above $620). BTCPay: zero, prompt hidden.
- Computed in the browser, at intent creation, and by `POST /api/{portal|public}/giving/quote`. Card vs ACH is chosen on our page; the Stripe session is restricted by `payment_method_types`.
- **Actual fees:** `gtxn_Fee` = processing fee from the charge's balance transaction or PayPal's `seller_receivable_breakdown`. Stripe books Billing as separate `stripe_fee` balance transactions, and standalone-fee accounts show 0 on the charge, so Billing is kept as `gtxn_BillingFeeEst` and exact totals come from payout lines (I7).
- The whole gross is the deductible gift; the receipt notes "includes $3.30 you chose to cover"; any fee shortfall is the church's.

**F10. Apple Pay and Google Pay (D5).** v1: Stripe's hosted Checkout shows them when enabled in the Dashboard, on eligible devices, with no domain registration, in subscription mode too; the Give tab labels Stripe "Card · Apple Pay · Google Pay". GIV-60: on-page Express Checkout needs HTTPS and `POST /v1/payment_method_domains` ("Register this domain"); registration is by hostname, so subdirectory installs work; Stripe no longer asks for a `.well-known` file. PayPal Apple Pay is deferred: approval plus a domain-association file at the domain root, which subdirectory installs and the Caddy example (dot-paths → 404, `docker/examples/frankenphp/Caddyfile:44-45`) cannot serve.

**F11. BTCPay.** The donor picks "Bitcoin" (fee and recurring hidden). `POST /api/v1/stores/{storeId}/invoices` `{amount: gross, currency}`, `metadata.orderId=gint_Uuid`, `checkout{speedPolicy, paymentMethods, expirationMinutes 15, redirectURL}` → redirect to `checkoutLink`. Webhooks: `BTCPay-Sig: sha256=HMAC(secret, raw)` with `hash_equals`, then re-fetch.

| Event / state | Result |
|---|---|
| `InvoiceProcessing` | `gint` processing; History "Confirming" |
| `InvoiceSettled` | F0: CRYPTO row in the BTCPay Online deposit, `plg_amount` = invoice fiat (bookkeeping); `gnc` quantity, rate, source, txids; flag > $5,000 |
| PaidOver, PaidLate, Marked, Expired + partial | review, no rows yet: **Accept as received** (GIV-40) or **Refund via claim link** (GIV-41) |
| `InvoiceExpired` unpaid / `InvoiceInvalid` | `gint` expired / failed |

Receipts and statements show asset, quantity, date and invoice/tx id, **no value**; excluded from cash totals (I10).

**F12. Fundraisers**
- **Balance:** `FundraiserBalanceService::forPaddle(pnId)` = Σ `di_sellprice` won + Σ `mb_count × di_sellprice` − Σ (`plg_amount` − `plg_FeeCovered`) with `plg_pn_ID` = that paddle; FMV = matching Σ `di_estprice`. Household view sums the family's paddles. Keying on the paddle, not on `plg_PerID`, means a payment entered as family-level (the only mode when `bFinanceShowGivenBy` = 0) or paid by the HoH still lowers the right balance.
- **Paying:** portal card "Spring Auction: you owe $180 [Pay now]" (own paddle; the HoH sees one card per household paddle, each with its own Pay now, so one payment covers one paddle). Staff "Send payment link" (`User::isManageFundraisersEnabled()`, or Finance): `Token` type `fundraiserPayment` with `reference_id` = `paddlenum_pn.pn_ID` (fixes fundraiser and buyer); a new `fundraiserPayment` case in `Token::build()` sets 30 days, 5 uses. It opens the public page locked (fund `fr_fund_ID`, amount = balance or less, no recurring, "Paying for {name}"), attributed to the buyer, and works even when the public page is off (F2.1). The buyer statement's "Credit card __ Exp __" line (`/fundraiser/{fundraiserId}/reports/statement`) becomes "Pay online" + QR.
- **Recording:** `plg_fr_ID` and `plg_pn_ID` from `gint_fr_ID`/`gint_pn_ID` (set from the token or the portal card); `plg_NonDeductible = min(payment − covered fee, FMV not yet covered)`; over $75 the receipt has the quid-pro-quo statement.
- **Fee cover:** offered on fundraiser payments like any gift, and treated as a gift on top. A buyer who owes $180 and covers the fee pays about $185.40; the balance goes to $0, not −$5.40, and the receipt says "includes $5.40 you gave to cover processing fees", which is deductible. Donation-style fundraisers use `?fundraiser={id}` (no paddle).
- **Admin:** editor Fundraiser select for cash/check; when a fundraiser is chosen a Paddle picker is required and sets `plg_pn_ID`, and "Given by" defaults to the paddle's buyer; **Collected** (Σ `plg_amount` − `plg_FeeCovered`, with covered fees shown beside it) beside **Raised**, which stays sell-price based (`FundRaiserService::getViewModel()`; list page `getListSummaries()`).

**F13. Deposits**
- **Batch (v1):** under `GET_LOCK`, find the open `dep_Type='Online'` deposit for the gateway and period (`sGivingDepositBatching`, `sTimeZone`) or create "{Gateway} online gifts, week of {date}", `dep_EnteredBy` NULL. A closed period's gift goes to a new open deposit (also late ACH and refunds). Test mode uses "[TEST] …" deposits.
- **Payout (Stripe, GIV-42):** rows wait with `plg_depID` NULL; `payout.reconciliation_completed` → `listPayoutLines()` → deposit on UQ(gateway, payout ref), date = arrival; attach only NULL-deposit rows (else discrepancy); set fee, net, recon status (I7).
- Closing fires `DEPOSIT_CLOSED`. `Deposit::preDelete()` (which deletes every row on the deposit today) refuses closed deposits (GIV-01) and gateway or gateway-row deposits (GIV-16). Gateway deposit type is fixed; reopening is audited; staff cannot create 'Online' deposits (the `depositType` enum on `POST /api/deposits`).
- **Test purge (Go live, GIV-15):** `GivingTestPurgeService` runs in one transaction, audited. It deletes only `gtxn` with `gtxn_Mode = 'test'`, their pledge rows, `gint`, `grec`, `gnc` and `gntf` rows, and deposits that hold only test-mode rows (the "[TEST] …" deposits), closed or not. It bypasses the delete guards above and `Pledge::preDelete()` on purpose; no other path does.
- The slip shows CRYPTO under "Non-cash property" at bookkeeping value, outside the cash total (D9).

**F14. Reconcile.** `GivingReconcileService` is a `SystemService::runTimerJobs()` step, a **Sync now** action, and a 2-call step after each webhook. Per run, ≤ 20 API calls:
1. Intents: `created` > 24 h → expired. `redirected` > 2 h with no processing event → `completeReturn()` → completed, processing or abandoned. `processing` intents wait for the gateway (never abandoned, not counted by the circuit breaker, not purged). When a recurring `gint` becomes abandoned or expired, its `pending` `grec` → expired (F3.6).
2. Re-fetch pending `gtxn`.
3. Catch up with `gwhe_Source=reconcile`: Stripe `GET /v1/events` (30-day window), PayPal `GET /v1/billing/subscriptions/{id}/transactions` and `GET /v1/notifications/webhooks-events`, BTCPay invoice search by `orderId`.
4. Retry failed `gwhe` (< 10 attempts), and apply `gwhe` held awaiting a parent that has since been recorded.
5. Refresh active `grec` daily.
6. Drain ≤ 50 notifications (GIV-17).
7. Check I1–I4 and I6–I11 (I5 is enforced at write time).
8. Purge per retention.

Cron is the supported scheduler (`src/cli/timerjobs.php`). Admin page loads are a fallback (`Footer.js` → `POST /api/background/timerjobs`), rate-limited by `iTimerJobsMinIntervalMinutes`; portal pages do not load `Footer.js`. Readiness reuses `sLastTimerJobsRunDateTime` (`SystemService::TIMER_JOBS_LAST_RUN_CONFIG`) and `iTimerJobsStaleHours` (`SystemService::isTimerJobsRunStale()`, shown on the admin dashboard): stale cron blocks live mode.

---

## 5. UI and reports

### 5.1 Portal Giving tabs
- **Nav and page:** the Giving page, its nav entry and `bPortalShowGiving` come from RFC 0004 (GIV-83), with its Pledges view. The tabs added here use the same portal permissions model (RFC 0004 A41): `portal.giving.give` and `portal.giving.history`, from the everyone rule while their feature switches are on; household history uses `portal.giving.household`. This RFC adds two tabs, URL per tab, with a `giving/partials/tabs.html.twig` partial like volunteer's. The Give tab needs the D1 gate, `bEnableOnlineGiving` and a gateway; History needs none. Portal home gets a Give card.
- **Routes:** `/portal/giving/give[?pledge=]`, `/portal/giving/history[?year=]`, `/portal/giving/history/{year}/statement.pdf|.csv`, `/portal/giving/return`, `/portal/give`, `/portal/give/return`.
- **Templates:** `giving/{give,history,return,public,public-return}.html.twig`; partials `tabs, recurring-notice, more-info-dialog, receipt, fund-lines`; all overridable (D8), documented with the portal template contract (`member-portal-design.md`). A `money` filter in `PortalExtension::getFilters()` over `CurrencyFormatter`. Dates preformatted in PHP.
- **API** `src/api/routes/portal/portal-giving.php` (created by RFC 0004): `quote, intents, intents/{uuid}, recurring/{uuid}, …/cancel, …/manage` behind `CSRFMiddleware` + `PortalApiMiddleware`; no person or family ids (the `portal-me.php` invariant). During Login as User (member sign-in §6.5), writes use a giving no-masquerade middleware: 409 "Giving is not available while viewing as another person", even when the church allows changes during Login as User. Bundle `portal-giving` with `portal-forms.ts`, `portal-toast.ts`, native `<dialog>`.
- **Give tab order:** recurring notices; fundraiser balance card; presets and fund lines (+ Add another fund, ≤ 5; `fun_Description` help); Give as (HoH); gateway/method (hidden if one); recurring panel with live sentence; fee cover with `aria-live` estimate; button "Give $103.30" (one-time) or "Give $104.05 monthly" (recurring, F9); non-HoH note "Gifts you make are also visible to your head of household"; privacy and refund links; TEST banner.
- **Giving History (D3):** RFC 0004's `GivingScope::forPortalActor()` (GIV-07): HoH (actor in `Family::getHeadPeople()`, which reads the `sDirRoleHead` list) → `plg_FamID = fam OR plg_PerID = actor`; others → `plg_PerID = actor`. Payments, online or office. Heading "Giving for the Smith household" or "Gifts credited to you — household gifts appear in the head of household's history". Calendar-year sections, newest first: total and per-fund subtotals; Date | Given by (HoH) | For | Method ("Online" badge) | Amount | Status; pending marked and excluded from totals; refunds negative; "Non-cash gifts" block without value or total.
- **Download:** "{year} statement" (PDF/CSV) is what `GivingStatementService` would mail that person under `sGivingStatementMode`; if the mode gives them none (non-HoH in Household) it is a "Giving summary (not a tax statement)". Current year is year-to-date.
- **Login as User** (member sign-in §6.5): reads only if the administrator `User::isFinanceEnabled()`; writes 409; audited with the administrator in `faud_ImpersonatorPerId`.

### 5.2 Public page
See F2. Framing stays forbidden (`X-Frame-Options: SAMEORIGIN`; cross-site iframes also lose the Lax cookie), so churches link to it, with `?fund=`/`?amount=` deep links and a QR builder. The Caddy example (`docker/examples/frankenphp/Caddyfile`) has no `/portal/*` handle on master, so portal paths fall to the root `index.php`; GIV-33 adds one if it is still missing.

### 5.3 Admin, finance, statements
- **`/admin/online-giving`** (admin): settings; webhook URLs; plugin settings links; Go live (test purge, F13); QR builder; install-identity prompt.
  - **Readiness, blocking live:** HTTPS from the configured `$URL`, not `X-Forwarded-Proto` (trusted blindly by `Bootstrapper::initSession()`); HTTPS for the BTCPay `serverUrl`; ISO currency matches `sCurrencySymbol` and the gateway account where exposed; ≥ 1 online fund; email enabled (`SystemConfig::isEmailEnabled()`); fresh cron; secrets key outside the DB; DB current; install identity confirmed; keys match mode.
  - **Warnings:** plugin head/footer scripts on giving pages (D8); no webhook in 7 days; privacy or refund URL empty.
- **Admin → Member Portal:** `bPortalShowGiving` is RFC 0004's (GIV-83).
- **`/finance/online-giving`** (`FinanceRoleAuthMiddleware`; Finance menu `Menu::getDepositsMenu()`, shown only when available). Built by GIV-40; later issues add their actions:
  - **KPIs:** 30-day gross, fees, net, count; active recurring count and monthly total.
  - **Transactions:** re-attribute; resend receipt (GIV-17); refund and erase guest PII (GIV-41).
  - **Recurring:** list; alerts for deceased or deactivated owners; staff cancel with TOTP, `CancelSource=staff`, donor emailed, and Resume from review (GIV-41).
  - **Needs review:** each reason has an action: Accept as received (records the received amount), or Refund (GIV-41).
  - **Undeposited.**
  - **Gateways:** health, mode, last webhook/sync, receive-only switch, Sync now.
  - **Households:** families with gifts but no living, active head.
- Finance dashboard card; family view Source column (the pledges and payments DataTable in `FamilyView.js`; the "Given by" column, the person Giving card and the fund toggle are RFC 0004's GIV-06 and GIV-80); `DepositSlipEditor.php` gross/fees/net; `PledgeDetails.php` "Online transaction" panel; paddle list Send payment link, Paid, Balance.

**Statements.** Moved to RFC 0004 §5 (`GivingStatementService`, the Household, Individual and Both modes, the tax-statements route and view, and the head-list fixes). This RFC adds: crypto in a non-cash section without value (GIV-24); covered fees included; refunds netted; the same service behind the portal downloads (GIV-30).

### 5.4 Reports
- Moved to RFC 0004 §5: the "Anonymous" label, the LEFT JOINs and refund netting in the pledge summaries (GIV-02), and the donor count (GIV-05). They are what make this RFC's negative rows net.
- Deposit PDF totals CREDITCARD, BANKDRAFT and CRYPTO (non-cash) beside the CHECK and CASH totals (`Deposit::getTotalChecks()`, `getTotalCash()` and the counts), plus an Online gross/fees/net summary. CSV adds person, source, gateway, external ref, fee. OFX uses `sGivingCurrency`.
- Advanced Deposit: CRYPTO label in the method blocks of all three sort layouts (deposit, fund, family) of `Reports/AdvancedDeposit.php`; Given by, Source, Gateway filters.
- **New (GIV-44):** online by gateway (gross, covered, fees, net); recurring forecast; past-due; disputes; non-cash register (8283/8282). #9313's per-person reports are RFC 0004's GIV-43.
- **Families:** RFC 0004 §5 covers family delete and the family donation move. This RFC adds: the move is refused while the family owns a pending, active, past_due or review recurring gift.

---

## 6. Security, PCI, privacy, receipts

| # | Threat | Control | Child PR (§7) |
|---|---|---|---|
| T1 | Card data reaches ChurchCRM | Hosted redirect; brand and last 4 only; paper card line removed | GIV-20, 22, 24, 51 |
| T2 | Forged webhook | Raw-body signature; re-fetch; mode and install checks after re-fetch | GIV-12, 16 |
| T3 | Duplicate or out-of-order events | UQ event id and external ref; row lock; state machine | GIV-16 |
| T4 | Tampered amount or fund | Sessions built from `gint`; mismatch → review | GIV-16 |
| T5 | IDOR, masquerade | UUIDs; actor-scoped queries; no id params; 409 on writes; impersonator audited | GIV-31, 32 |
| T6 | Card testing | Stripe's hosted checks; per-session 3 failures/15 min; per-email 5 intents/h; per-IP 60/10 min via `ClientIp`; honeypot; $5 minimum; generic declines; circuit breaker emails admins; Turnstile in GIV-60 | GIV-11, 33 |
| T7 | CSRF bypass via `X-API-Key` | `StrictCSRFMiddleware` | GIV-11 |
| T8 | Secret leak | Encrypted, write-only; never in `PluginManager::getPluginsClientConfig()`, hooks, audit, logs; writes checked against the manifest (`POST /plugins/{pluginId}/settings` in `src/plugins/routes/api/management.php`); `rk_` keys; key outside DB | GIV-13, 15 |
| T9 | Gateway disabled mid-subscription | `canDeactivate()`, receive-only, 503 + alert, catch-up | GIV-12 |
| T10 | Test money in books | Mode on rows; go-live purge; clone detection | GIV-15 |
| T11 | Insider fraud | I4; original-method refunds; TOTP step-up; audit; alert | GIV-03, 41 |
| T12 | Deposit delete wipes gifts | Delete guards | GIV-01, 16 |
| T13 | Wrong year, rounding | UTC → `sTimeZone`; server FY; integer cents | GIV-04, 16 |
| T14 | No HTTPS | Readiness from `$URL` and the BTCPay `serverUrl`; HTTPS check on the live `checkoutLink`; no `apiKey` across an HTTPS-to-HTTP redirect | GIV-15, 24 |
| T15 | Open or rewritten redirect | Server-built URLs; host allow-list (D8); login location validated by `AuthenticationManager::validateRedirectPath()` | GIV-31, 33 |
| T16 | Leaky errors | Generic JSON; no bodies logged; `giving` channel | GIV-12 |
| T17 | Gateway impersonation | `register(owner, gateway)`; core decided by path; no community id may equal a core id | GIV-10 |
| T18 | Webhook during upgrade | No auto-upgrade in webhook boot; 503 | GIV-12 |

**PCI.** Redirect pages host no payment fields: SAQ-A, and FAQ 1588's script criterion applies only to pages embedding a provider's form. Controls on overridable pages (D8): server-built redirect, host allow-list, readiness warning. GIV-60 adds, for a small non-overridable payment partial only: enforced page CSP, SRI, no plugin/theme JS in the partial, CSP reports as admin alerts, script inventory; Stripe.js from `js.stripe.com` via `@stripe/stripe-js/pure`, never bundled.

**Idempotency roots:** Stripe `gint-{uuid}-create`, `cancel:{uuid}`, `refund:{id}:{n}`; PayPal `PayPal-Request-Id` `capture:{uuid}` (capture on return and on `CHECKOUT.ORDER.APPROVED`); BTCPay `orderId`.

**Logging.** New `LoggerUtils::getGivingLogger()` like the auth logger (`LoggerUtils::getAuthLogger()`, which adds no PostHog handler; `getAppLogger()` does); no names, emails or amounts in messages or context. Giving tables hold IPs only as HMACs, keyed outside the DB (§3.4); app and Slim logs keep plaintext `REMOTE_ADDR` (the request context in `LoggerUtils`; `SlimUtils::renderErrorJSON()` and `registerDefaultJsonErrorHandler()`) for 3 days.

**Privacy**
- No auto-matching of anonymous gifts; staff re-attribute only on request, audited.
- A guest's email sits on `gint_DonorEmail`, `gntf_ToEmail` and the email log. `BaseEmail` logs every send through `EmailLogService::logSend()`, which matches an address sent with no person or family id to one (`resolveAddress()`). Anonymous receipts pass a "do not resolve" flag, so the log never links a guest to a person.
- **Erase guest donor PII** (Finance, audited) redacts email and name on `gint`, `gntf`, email-log rows and stored `gwhe` payloads; money stays.
- **Person delete:** RFC 0004 (GIV-05) clears `plg_PerID` and refuses while a payment of theirs has `plg_FamID` NULL. This RFC also clears `gtxn_per_ID`, `gint_per_ID`, `grec_per_ID` and `gcus_per_ID` (audited), and refuses while the person owns a pending, active, past_due or review recurring gift. Family delete: §5.4.
- **Retention:** pledge rows, `gtxn`, `gnc`, `faud` permanent · `gwhe_Payload` 90 days · IP hashes 30 days · abandoned `gint` 180 days · sent `gntf` 1 year.

**Receipts (US).** Organisation, amount, date, number, goods-and-services wording. Each receipt is the contemporaneous acknowledgment for a single gift of $250+ (gifts are not aggregated). Over $75 with goods: deductible limited to the excess over the good-faith FMV (`di_estprice`). Crypto is property: asset and quantity, no value; > $5,000 flagged for Form 8283 Section B (qualified appraisal, church signs the donee part); disposal within 3 years tracked for Form 8282.

**Sources (checked 2026-09-24):** https://raw.githubusercontent.com/php/frankenphp/main/frankenphp.stub.php · https://www.php.net/manual/en/ref.fpm.php · https://github.com/paypal/PayPal-PHP-Server-SDK · https://docs.stripe.com/apple-pay?platform=web · https://docs.stripe.com/payments/payment-methods/pmd-registration · https://docs.stripe.com/reports/balance-transaction-types · https://docs.stripe.com/standalone-fees · https://docs.stripe.com/payments/checkout/billing-cycle?payment-ui=stripe-hosted · https://developer.paypal.com/api/rest/webhooks/event-names/ · https://developer.paypal.com/api/subscriptions/v1/subscriptions-transactions · https://docs.btcpayserver.org/Subscriptions/ · https://raw.githubusercontent.com/stripe/stripe-php/master/composer.json · https://blog.pcisecuritystandards.org/faq-clarifies-new-saq-a-eligibility-criteria-for-e-commerce-merchants · https://mailchimp.com/developer/marketing/api/list-members/add-or-update-list-member/ · https://www.irs.gov/pub/irs-pdf/p1771.pdf · https://www.irs.gov/instructions/i8283

---

## 7. Proposed breakdown

Nothing here is filed yet (D12). One concern and one PR per child; each user-visible child opens a user-manual docs issue. Each issue will carry its own scope and acceptance criteria. CI uses `FakeGateway` (`CHURCHCRM_GIVING_SANDBOX=1`, §2.4), never a real gateway.

**Sequencing:** RFC 0004's prerequisites (GIV-02 to GIV-07) come first. Phases B–D, F and GIV-50 can then go upstream before the donor surfaces. Phase E and GIV-51 wait for RFC 0004's Giving page (GIV-83). Online giving stays hidden until GIV-20 to GIV-24 are merged (D1); office-side work (GIV-01, GIV-50) is visible as soon as it merges.

| Id | Title | Design | Depends on |
|---|---|---|---|
| **A** | **Finance foundations (no gateway)** | | |
| GIV-01 | Remove dead e-payment code (`setDeposit`, Load Authorized, Run Transactions); refuse deletes in closed deposits; fire `DEPOSIT_CLOSED` on close | F13, §2.4 | – |
| GIV-02 | Moved to RFC 0004 (anonymous = NULL family) | | |
| GIV-03 | Moved to RFC 0004 (finance audit log) | | |
| GIV-04 | Moved to RFC 0004 (`PledgeWriter`). `Person::ONLINE_GIVING` (A17) moves to GIV-16 | | |
| GIV-05 | Moved to RFC 0004 (per-person attribution, #9313) | | |
| GIV-06 | Moved to RFC 0004 ("Given by" UI, D11 move) | | |
| GIV-07 | Moved to RFC 0004 (`GivingStatementService`, `GivingScope`) | | |
| **B** | **Platform** | | |
| GIV-09 | Plugin-local `vendor/` (D10) | §2.6 | – |
| GIV-10 | Contract; registry (lazy owner check, core by path, no community id equal to a core id); `payments.process`; release gate; `FakeGateway` fixture | §2.2–2.4 | – |
| GIV-11 | Rate limiter, `ClientIp`, `StrictCSRFMiddleware`, circuit breaker | §6 T6–T7 | – |
| GIV-12 | Webhook ingress in the plugin folder; webhook boot mode (no session, no auto-upgrade, no PostHog handler); DB gate; lifecycle guard; `getGivingLogger()` | §2.5 | 10, 11, 14 |
| GIV-13 | Encrypted `secret` settings with the key outside the DB; giving HMAC key derivation | A15, §3.4 | – |
| GIV-14 | Giving schema: §3.1 columns (incl. `plg_fr_ID`, `plg_pn_ID`, `plg_PledgeID`), §3.2 tables, utf8mb4 | §3 | 05 |
| GIV-15 | `/admin/online-giving`, settings, readiness, test purge, `FeeCalculator`, `AllocationSplitter`, `RecurrenceScheduler`, install identity | §3.4, §5.3, F3, F9, F13 | 10, 13, 14 |
| **C** | **Engine** | | |
| GIV-16 | Intents; `GivingRecorder`; `WebhookProcessor`; batch deposits; gateway-group and gateway-deposit guards; `Person::ONLINE_GIVING`; reconcile; nightly invariants | F0, F13, F14, §2.5 | 04, 12, 14, 15 |
| GIV-17 | Notification outbox (flush after commit, drain in reconcile) and receipts | §3.2 `gntf`, §6 | 16 |
| **D** | **Gateways (all three before release, D1)** | | |
| GIV-20 | Stripe, one-time | §2.3, F10 | 09, 13, 16, 17 |
| GIV-21 | Stripe recurring; `RecurringGiftService` | F3–F6 | 20 |
| GIV-22 | PayPal, one-time (Orders v2) | §2.3, F6 | 13, 16, 17 |
| GIV-23 | PayPal recurring (Subscriptions v1) | F3–F6, D7 | 21, 22 |
| GIV-24 | BTCPay Server; non-cash recording | F11 | 13, 16, 17; 07 for the statement section |
| **E** | **Donor surfaces (on RFC 0004's Giving page)** | | |
| GIV-30 | History tab and statement downloads | §5.1 | 07, 83 |
| GIV-31 | Give tab, one-time; Pay my pledge | F1, §5.1, D22 | 30, 11, 16, 17, one of 20/22/24 |
| GIV-32 | Recurring in the portal: notices, More Info, Cancel | F3, F5 | 31, 21 or 23 |
| GIV-33 | Public anonymous page | F2, §5.2 | 31 |
| GIV-34 | Removed 2026-10-05: no newsletter sign-up (D4) | – | – |
| **F** | **Finance operations** | | |
| GIV-40 | Finance dashboard and review queue | §5.3 | 03, 16, 17 |
| GIV-41 | Refunds, disputes, staff cancel and Resume, guest PII erase (TOTP) | F7, §6 | 40; 20 or 22; 21 or 23 for staff cancel |
| GIV-42 | Stripe payout deposits; deposit slip and exports | F13, §5.4 | 16, 20 |
| GIV-43 | Moved to RFC 0004 (#9313's per-person reports) | | |
| GIV-44 | Online giving reports (split from GIV-43) | §5.4 | 07, 16, 24 |
| **G** | **Fundraisers** | | |
| GIV-50 | Buyer balances by paddle; editor paddle picker; Collected | F12 | 14 |
| GIV-51 | Online balance payments; pay links | F12 | 17, 31, 33, 50 |
| **H** | **Later and cross-cutting** | | |
| GIV-60 | Embedded checkout, on-page wallets, CSP builder, Turnstile | D5, D8, F10 | 20, 22, 24, 31 |
| GIV-70 | Docs, E2E, i18n (rolling, shared with RFC 0004) | – | each item after the issue it covers |
| GIV-71 | Legal checklist and policy links | §6 | 15, 17 |

---

## 8. Open questions

### 8.1 Product defaults

| # | Question | Recommended default |
|---|---|---|
| Q1 | Who can "Give as household"? | HoH only; spouses give as themselves. |
| Q2 | Whose recurring gifts show as notices? | Only those the actor set up; staff can cancel any. |
| Q3 | Where does a covered fee go? | Spread across chosen funds; designated fund optional. |
| Q4 | "Cover the fee" default? | Unchecked. |
| Q5 | Moved to RFC 0004 (product default 2: statement mode). | |
| Q6 | Refund alert threshold? | $500. |
| Q7 | Amount limits? | Public $5–$10,000; members $1–$25,000. |
| Q8 | Fee defaults? | Standard published rates, with nonprofit-rate help. |
| Q9 | PayPal "Last day"? | Hidden until a sandbox test proves month-end billing. |
| Q10 | Receipt for every recurring charge? | Yes. |
| Q11 | Moved to RFC 0004 (product default 4: nav position). This RFC adds a Give card on the portal home. | |
| Q12 | No HoH or several heads? | RFC 0004 (product default 3): all of `getHeadPeople()` see the household. This RFC adds the office list of headless families with gifts. |
| Q13 | Fundraiser pay links? | 30 days, 5 uses; Manage Fundraisers or Finance may send. |
| Q14 | Stripe ACH? | Included, off. |
| Q15 | Batch period? | Weekly; payout mode stays opt-in. |
| Q16 | Masquerade reads of History? | Only for finance-enabled impersonators. |
| Q17 | TOTP step-up for staff cancels too? | Yes. |
| Q18 | Deceased or deactivated recurring owner? | Alert only; staff decide. |
| Q19 | Test-mode gifts? | "[TEST]" deposits, purged at go-live. |

### 8.2 For the maintainer
The questions are listed at the top of this document, under "Questions for the maintainer". Question 6 is answered in #10033.

---

## 9. Features beyond the core requirements

| Feature | Proposal |
|---|---|
| Receipts with IRS wording and numbers; printable on-screen receipt; portal year statement | Keep (GIV-17/30) |
| Split gifts; refunds, disputes, chargebacks as negative rows; review queue | Keep (GIV-31/40/41) |
| Automatic deposits with gross/fees/net; payout-exact deposits (opt-in); catch-up sync and webhook health | Keep (GIV-16/42) |
| Test mode with purge and clone detection; encrypted secrets; core rate limiter (the finance audit log is RFC 0004's GIV-03) | Keep (GIV-15/13/11) |
| Plugin-disable guard and receive-only; delete guards; past-due badge with Update payment method | Keep (GIV-12/21/32) |
| HoH transparency note; privacy and refund links; deep links and QR | Keep (GIV-31/71/33) |
| Paper card-number line removed from fundraiser statements | Keep (GIV-51) |
| WCAG 2.2 AA; gateway locale | Keep, as acceptance criteria |
| Recurring edit/pause; biweekly; dunning emails (gateways send their own); website iframe | Defer |
| PayPal Apple Pay; BTCPay recurring; automated BTCPay refunds; more gateways (#9575) | Defer; the contract fits |
| Import from other platforms (#9600) via `PledgeWriter`, deduplicated on external ref | Defer, aligned |
| Pay my pledge, linking a payment to an RFC 0004 pledge | Keep (D22, GIV-31) |
| Campaigns, tribute, text-to-give, kiosk; stock and in-kind gifts (reuse `gnc`) | Defer |
| CRA/Gift Aid; multi-currency; bulk statement email; two-person refund approval; QuickBooks export; paid events (#8181) | Defer |
| Dropping `plg_aut_*`, `result_res` | Defer (release after GIV-01) |
| Staff virtual terminal | Reject (PCI scope) |

---

## Appendix A. Requirements this design covers

Each requirement and each proposed product choice (D1–D12, D22), with the sections that meet it. Rows marked "Moved to RFC 0004" are met there.

| # | Requirement | Where | Notes |
|---|---|---|---|
| R1 | Payment gateway plugin design | §2.1–2.6, A20, GIV-10 | |
| R2 | Anonymous giving, admin entry | Moved to RFC 0004 | |
| R3 | Anonymous giving, unauthenticated portal page | F2, §5.2, GIV-33 | `/portal/give`, outside `PortalAccessMiddleware` |
| R4 | Giving by persons, admin entry | Moved to RFC 0004 | |
| R5 | Giving by families, admin entry | Moved to RFC 0004 | |
| R6 | Giving by persons, portal | F1 | PerID = actor |
| R7 | Giving by families, portal | F1, §5.1, Q1 | Give as household |
| R8 | Portal Giving page, tabs Give and Giving History | §5.1 | On RFC 0004's page; the Give tab hides when giving is off |
| R9 | Give: electronic gift via enabled gateways | F1, §2.4 | `forGiving()` |
| R10 | Give: choose purpose | F1, §5.1, `fun_OnlineGiving` (RFC 0004 D14) | Up to 5 funds |
| R11 | Give: estimate fee to the church | F9 | Actual fees stored separately |
| R12 | Give: ask to cover the fee | F9, §5.1 | Hidden when zero |
| R13 | Give: offer recurring | F3, §5.1 | |
| R14 | Monthly default | F3.2 | |
| R15 | Weekly or annually | F3, `frequencies` | |
| R16 | Pick the day of the month | F3, D6 | 1–28 + Last day |
| R17 | Recurring only where supported | §2.3, F3.1, F1.3 | Server rejects unsupported recurring (422) |
| R18 | Stripe Subscriptions | F3, F4, GIV-21 | |
| R19 | PayPal recurring profiles | D7, GIV-23 | Subscriptions v1 |
| R20 | PCI compliant | D5, D8, A1, §6 | SAQ-A; host allow-list |
| R21 | Gateway runs recurring charges | F4 | |
| R22 | Webhook in the plugin's folder | §2.5, GIV-12 | Core route only if plugin-folder routes are refused |
| R23 | Webhook records results | F0, F4, F6, F14 | Catch-up for all three |
| R24 | Several recurring gifts per user | F5, GIV-32 | |
| R25 | Shown on Give with More Info as a reminder | F5.1, §5.1 | Top of the tab; no notice for an abandoned checkout |
| R26 | More Info modal | F5.2 | `<dialog>` |
| R27 | Modal: set-up date | F5.2 | |
| R28 | Modal: last charged | F5.2 | "Not yet charged" |
| R29 | Modal: next charge | F5.2 | |
| R30 | Modal: subscription id | F5.2 | Copyable |
| R31 | Modal: amount | F5.2 | With funds and fee |
| R32 | Modal: Cancel | F5.2 | |
| R33 | "Are you sure?" | F5.3 | Yes / No |
| R34 | Yes cancels at the gateway | F5.4, I8 | No lock during the call |
| R35 | Yes marks ours Cancelled | F5.4 | CancelSource |
| R36 | Refresh clears the notice | F5.5 | |
| R37 | History lists gifts | §5.1 | Online and office |
| R38 | By calendar year | §5.1 | |
| R39 | Totals by year | §5.1, I10 | Pending and crypto excluded |
| R40 | Non-HoH sees own gifts only | D3, A10 (RFC 0004), §5.1 | Spouse included |
| R41 | HoH sees whole family | D3, A10, D11 (RFC 0004), §5.1 | Incl. family-level |
| R42 | Admin reports updated similarly | Moved to RFC 0004; online reports §5.4, GIV-44 | |
| R43 | Fundraisers use gateways | F12, GIV-50/51 | Token on `pn_ID`; balance by `plg_pn_ID`; works with the public page off |
| R44 | Portal Apple Pay where allowed | D5, F10, GIV-60 | v1 on Stripe's hosted page |
| R45 | Other features and integration | §8, §9 | |
| R46 | Three gateways in the first release | D1, §2.4, §7 D | Release gate |
| R47 | Core contract, core plugins | §2.1, §2.6, D10 | SDK in plugin vendor |
| R48 | Public donor stays anonymous | F2.5, §6 | Email log not resolved |
| R49 | Optional receipt email on the public page | F2.2, F2.4, GIV-17 | Signed return token |
| R50 | Removed 2026-10-05: no newsletter sign-up | D4 | |
| R51 | "If you are a member, click here" link | F2.2 | Shown only when the portal Give tab is available; plus "Contact the church office" |
| R52 | Login returns to the Give page | F2.6 | `AuthMiddleware::redirectToLogin()` |
| R53 | `<next>-*.sql`, not in `upgrade.json` until scheduled | §3 | Question 4 |
| D1 | Three gateways; release gate | §2.4, §7 | |
| D2 | Upstream; core contract; core plugins | §2.1, §2.6 | |
| D3 | HoH / non-HoH visibility | Moved to RFC 0004; applied in §5.1 | |
| D4 | Public page, receipt, member link | F2 | |
| D5 | Hosted redirect; wallets on Stripe | A1, F10 | |
| D6 | First charge on chosen day | F3, A27 | |
| D7 | PayPal Subscriptions v1 | §2.3, F3, GIV-23 | |
| D8 | Overridable pages; server-built redirect | A26, F1.4, §6 | |
| D9 | Single 'Online' type | A7, §3.1, F13 | |
| D10 | Plugin-local vendor | §2.6, GIV-09 | |
| D11 | Moving families | Moved to RFC 0004; F4 for recurring charges | |
| D12 | Proposal first; issues only after the direction is accepted | Status, §7 | |
| D22 | Pay my pledge | A40, F0, F1, GIV-31 | Pledge and subscription stay separate |
