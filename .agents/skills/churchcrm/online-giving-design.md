# Online Giving and Payment Gateways — design (proposed, target 7.8.0)

## Summary
- Members give once or on a schedule (weekly, monthly, annually) from a new **Giving** page in the Member Portal (#8977), and see their giving by calendar year.
- Visitors give anonymously from a public page, with an optional email receipt.
- Stripe, PayPal and BTCPay Server are core plugins behind a gateway contract in core. v1 sends donors to each gateway's hosted page, so no card data reaches ChurchCRM.
- The gateway runs recurring charges and reports each one to a webhook served from the plugin's own folder.
- Every gift is recorded once into the existing `pledge_plg` / `deposit_dep` tables. There is no second ledger.
- A gift can be credited to a person as well as a family (#9313), in office entry too. Statements, deposits (gross, fee, net), refunds, disputes, fundraiser balances and reports build on that.

## Questions for the maintainer
1. A core gateway contract plus core gateway plugins, revising #8178's adapter plugin (§2.1)?
2. Public webhook routes inside plugin folders, dispatched before `LoadConfigs.php` (§2.5)? Fallback: a core `POST /api/public/giving/webhook/{gatewayId}`.
3. Plugin-local `vendor/` built at release (D10, §2.6), or `stripe/stripe-php` in `src/composer.json`?
4. Target 7.8.0, with `7.8.0-*.sql` scripts added to `upgrade.json` only when you open that block (§3)?
5. Phases A–D, F and GIV-50 before #8977, and the portal children after #8977 and #9843 (§7)?
6. Should the per-person attribution here (GIV-05, GIV-06, GIV-07, GIV-43) replace #9313's scope, or should #9313 stay separate?

**Status**
- Proposed. No code yet. The child issues in §7 are opened only if this direction is accepted.
- Related: #9313 (per-person gifts), #8178, #8180 (plugin gateways), #9600, #9472, #9575, #8977 (Member Portal), #9225, #8181.
- Depends on unreleased work: #8977 Member Portal, #9843 masquerade, #9876 email log.
- Citations are `path:line` on upstream `master` `7881b01a2`. Code that exists only on those unreleased branches is marked **[portal branch]**.

**Today:** no gateway code (removed in #4566; dead callers at `src/DepositSlipEditor.php:55-58, 268-276`); gifts are family-level only (`src/mysql/install/Install.sql:552-579`).

**How to read this**
- Ids: **D** proposed product choice; **A** proposed technical choice; **F** flow (§4); **I** invariant (§3.3); **Q** open question (§8.1); **T** threat (§6); **R** requirement (Appendix A); **GIV-nn** a proposed child PR (§7), not filed.
- Table short names (§3.2): `gint` = `giving_intent_gint`, `gtxn` = `giving_transaction_gtxn`, `grec` = `giving_recurring_grec`, `gcus` = `giving_customer_gcus`, `gwhe` = `giving_webhook_event_gwhe`, `gntf` = `giving_notification_gntf`, `gcon` = `giving_consent_gcon`, `gnc` = `giving_noncash_gnc`, `faud` = `finance_audit_faud`, `srl` = `system_ratelimit_srl`.
- Tags: **[portal branch]** = code only on the unreleased branches above; **[#NNNN]** = needs that unmerged work (#8977, #9843 or #9876).
- Terms: HoH = head of household; UQ = unique key; PI = Stripe PaymentIntent; FMV = fair market value of goods the donor received; ACH = US bank debit; TOTP = authenticator-app code; SAQ-A = the shortest PCI DSS self-assessment, for merchants that hand all card entry to a provider; FAQ 1588 = the PCI Security Standards Council FAQ on SAQ-A script criteria; GDPR = EU data protection law; CASL = Canada's Anti-Spam Legislation.

---

## 1. Goals and non-goals

**Goals**
1. Core gateway contract; Stripe, PayPal and BTCPay as core plugins, each serving its webhook from its own folder.
2. Every gift credited to a person, a family or no one, from admin entry, portal or public page.
3. Portal **Giving** page with two tabs. **Give:** funds (split allowed), fee estimate and offer to cover, recurring (Monthly default; Weekly or Annually; chosen day) where supported, recurring notices with More Info and Cancel, Apple/Google Pay where the gateway allows. **Giving History:** by calendar year with totals, scoped by D3.
4. Public anonymous give page (D4).
5. Statements (Household, Individual, Both) and #9313's per-person reports.
6. Fundraiser balances payable online.
7. Deposits with gross, fees and net; review queue; refunds; disputes; webhook health.
8. US-compliant receipts; crypto as non-cash property.

**Non-goals (v1):** a second ledger; multiple currencies; recurring on the public page; Omnipay (no subscriptions support); everything marked Defer or Reject in §9.

---

## Proposed product choices

| # | Choice |
|---|---|
| D1 | The first release ships **Stripe, PayPal and BTCPay Server** as gateway plugins. **Release gate:** online giving stays hidden until all three are merged. Reason: the contract is proven against three different payment models (card processor, PayPal account, self-hosted crypto) before any church relies on it, and a church picks its gateway once, because recurring gifts cannot be moved from one gateway to another. |
| D2 | Upstream epic. The Giving domain and gateway contract live in core. Gateways are core plugins in `src/plugins/core/{stripe,paypal,btcpay}/`. |
| D3 | Anyone who is not Head of Household (HoH), **spouse included**, sees only gifts credited to them. The HoH sees the whole family, including gifts recorded to the family as a whole. |
| D4 | The public page is anonymous. An optional email gets a receipt. "Join our newsletter" is **checked by default** and **single opt-in** (subscribed at once); a setting makes it unchecked or hidden for EU and Canadian churches. The page links "If you are a member, click here" to `/portal/giving`, which requires login and returns the member there. Reason for the checked default: US law (CAN-SPAM) needs no prior opt-in, so a checked box is lawful there and grows the church's list; where it is not valid consent (GDPR, CASL) the setting makes it unchecked or off, and its help text says so (§6 Consent). |
| D5 | v1 uses **hosted redirect** for all three gateways (Stripe Checkout, PayPal approval page, BTCPay checkout link). Apple Pay and Google Pay appear on Stripe's hosted page, including for recurring gifts. On-page wallet buttons come later (GIV-60). |
| D6 | **The first recurring charge falls on the chosen day**, not today unless today is that day. The UI reads "First gift on Oct 1, then monthly on the 1st". Days 1–28, plus "Last day". |
| D7 | PayPal recurring uses **Subscriptions v1 REST**, the current replacement for PayPal's older NVP "Recurring Payment profiles". |
| D8 | Give, public and return pages **stay overridable** by themes like any portal template. With hosted redirect the PCI SAQ-A script criterion does not apply (FAQ 1588 covers embedded iframes, not redirects). Remaining control: the server builds the redirect URL and accepts only the gateway's hosts. Stricter script controls come only with GIV-60 and cover only a small payment partial, not the layout. |
| D9 | Deposits get **one new `dep_Type`, 'Online'**, no 'Crypto'. `dep_Gateway` separates BTCPay deposits. Crypto handling (non-cash, excluded from cash totals, no value on receipts) follows from `plg_method = 'CRYPTO'` and the row's `giving_noncash_gnc` record. |
| D10 | **Gateway SDKs live inside the plugin.** The loader optionally loads `src/plugins/<core\|community>/<id>/vendor/autoload.php`. Stripe's own `composer.json` holds `stripe/stripe-php`, which needs no other Composer package, so no version conflicts. The release build runs `composer install` in each plugin with a `composer.json`; Dependabot gets one entry per plugin. PayPal and BTCPay use core's Guzzle (`php-http/guzzle7-adapter`, `src/composer.json:46`). Why not `stripe/stripe-php` in `src/composer.json`: every install would ship and update a payment SDK even if it never takes online gifts, and a community gateway plugin could not bring its own SDK the same way. Cost: a loader change, a per-plugin install step in the release build, a CI duplicate-package check and one Dependabot entry per plugin (GIV-09). If this is refused, the fallback is `stripe/stripe-php` in `src/composer.json` (question 3). |
| D11 | **Adult child who starts a family:** gifts credited to them while in the parents' family stay in the parents' history; nothing is rewritten. The parents' HoH still sees them, the child sees them through the person credit, past statements stay consistent. New and recurring gifts go to the new family from the next charge, because the family is looked up at charge time. When staff change a person's family they get an optional, audited "Move this year's gifts to the new family" for the current calendar year, which has no statement yet. |
| D12 | This design is posted for feedback only, with one proposal issue. No epic or child issues are filed until the maintainer accepts the direction. |

## Proposed technical choices

| # | Choice | Reason |
|---|---|---|
| A1 | Hosted redirect in v1 (D5); embedded modes via per-gateway `CheckoutMode` in GIV-60. | SAQ-A; no CSP change in v1. |
| A2 | `giving_intent_gint` (attempt; source of truth for the fund split) and `giving_transaction_gtxn` (ledger). | Abandoned attempts stay out of the ledger. |
| A3 | Pledge rows link by `plg_gtxn_ID`; gateway GroupKeys start `gtxn{id}`. | Trivial immutability guard; GroupKey is never parsed. |
| A4 | Pledge columns stay `decimal(8,2)` (`Install.sql:557,572`; `orm/schema.xml:506,521`); new tables use wider `decimal(10,2)`; every entry point caps a gift at 999,999.99; integer cents; 2-decimal currencies only. | No overflow when split into pledge rows. |
| A5 | One Stripe "ChurchCRM Gift" product per mode, priced inline; split kept locally. | No product per fund. |
| A6 | Weekly batch deposit per gateway; Stripe payout mode opt-in later (GIV-42). | Needs no payout event, so it works when webhooks arrive late or only through catch-up (F14). |
| A7 | `plg_method` adds only 'CRYPTO' (card, wallets, PayPal = CREDITCARD; ACH = BANKDRAFT); `dep_Type` adds only 'Online' (D9). | Payouts mix methods; smallest enum change. |
| A8 | `PluginWebhookApp` dispatched at the top of `src/plugins/index.php`, before `Include/LoadConfigs.php`, in a session-less boot mode. | No auth, session, `bLockURL` redirect or DB auto-upgrade in a callback. |
| A9 | Inline processing, 10 s budget, 503 on transient failure. | `fastcgi_finish_request()` exists on PHP-FPM and FrankenPHP (alias of `frankenphp_finish_request()`), not on Apache mod_php. |
| A10 | HoH: `plg_FamID = :fam OR plg_PerID = :actor`; others: `plg_PerID = :actor`. | D3. |
| A11 | Refund rows dated on the refund date, in the open deposit; re-issue flag across years. | Closed periods never change. |
| A12 | Covered fee spread by largest remainder (leftover cents go to the lines with the largest fractional parts; ties to the larger line, then the lower fund id); designated fund optional. | Fund shares stay honest. |
| A13 | Separate `gntf`, `gcon`, `gcus`, `gnc` tables. | Own lifecycles and retention. |
| A14 | Core append-only `finance_audit_faud`. | Edits delete and re-insert; logs last 3 days (`LoggerUtils.php:19`). |
| A15 | Encrypted `secret` type (Defuse, `src/composer.json:38`), key from `Include/Config.php` or env; live blocked without it. | A DB-stored key like `sTwoFASecretKey` (`src/Include/LoadConfigs.php:57-62`) protects nothing in a dump. |
| A16 | `RateLimitService` + `ClientIp` (trusted proxies); per-session, per-email, circuit breaker; high per-IP ceiling; Turnstile deferred to GIV-60. | Sunday Wi-Fi shares an IP; Turnstile needs the GIV-60 CSP builder. |
| A17 | Automatic deposits `dep_EnteredBy` NULL (unsigned, `Install.sql:36`); system rows `plg_EditedBy = Person::ONLINE_GIVING (-3)` (signed, `Install.sql:562`). | −3 sits beside the existing system ids `SELF_REGISTER`/`SELF_VERIFY` (`Person.php:31-32`), so system writes are recognisable. |
| A18 | Default fees = standard published domestic rates. | International or currency-converted cards cost more (Stripe adds about 1.5% for international cards and 1% for conversion); any shortfall is the church's (F9). |
| A19 | Refunds and staff cancels need Finance + a **TOTP re-prompt** (`User::isTwoFACodeValid()`, `User.php:860`). | Enrolment is not presence. |
| A20 | `PaymentGatewayRegistry::register(PluginInterface $owner, PaymentGatewayInterface $g)`, not an open filter, verified after plugin loading ends; "core" decided by path; `payments.process` tag; CI flag `CHURCHCRM_GIVING_SANDBOX` for `FakeGateway` (§2.4). | A filter lets any plugin replace or impersonate a gateway. |
| A21 | `GIFT_RECORDED` once per group from `PledgeWriter`; `DONATION_RECEIVED` stays per row. | Admin and online gifts look alike. |
| A22 | Recurring change/pause and biweekly deferred. | The donor cancels and sets up a new gift instead, which avoids per-gateway proration and price-change rules. |
| A23 | Webhook payloads 90 days, encrypted. | Dispute window; payloads hold PII. |
| A24 | Reconcile in `runTimerJobs()` plus a bounded post-webhook step. | Rare staff logins still catch up. |
| A25 | Plugin-local `vendor/` (D10); PayPal and BTCPay over REST. | No gateway SDK in core. |
| A26 | Overridable pages (D8); server-built redirect; host allow-list on server and client; readiness warns about plugin head scripts. | Blocks a rewritten redirect URL. |
| A27 | One `RecurrenceScheduler` for anchor, first-charge date and UI sentence (D6). | UI and gateway always agree. |
| A28 | Single opt-in newsletter, default checked, setting unchecked/off, evidence stored (D4). | D4. |
| A29 | Stable `sGivingInstallId` UUID in metadata; a cloned DB forces test mode. | A URL hash would orphan subscriptions after a domain change. |
| A30 | Public page `/portal/give` with anonymous-safe Twig and a signed return token. | Themes reused; no session needed after checkout. |
| A31 | Attribution set when recorded and changed only through the audited attribution service (F8); recurring charges use the owner's family at charge time (D11). | History never shifts silently. |
| A32 | One `GivingStatementService` for admin and portal: visibility follows D3, statement placement follows the mode. | Each gift on exactly one tax statement. |

---

## 2. Architecture

### 2.1 Layers

```
Portal /portal/giving[/history]*   Public /portal/give*   Finance /finance/online-giving   Admin /admin/online-giving
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
\* Portal routes depend on #8977 [portal branch].

`PledgeWriter` (in `ChurchCRM\Service`, used by office entry too) is extracted from `FinancialService::insertPledgeorPayment` (`src/ChurchCRM/Service/FinancialService.php:373-444`) and `updatePledgeOrPayment` (`:495-557`) and runs without a session.

**Why the contract is in core (revising #8178's adapter plugin):** plugins are discovered core-first then community, unsorted within each directory (`discoverPlugins()`, `src/ChurchCRM/Plugin/PluginManager.php:71-109`, `DirectoryIterator` `:84`), booted in that order (`loadActivePlugins()`, `:114-148`), and autoloadable only once loaded (`:202-229`), so a gateway could boot before a plugin holding the interface. The callers are core. Precedent: `SystemCalendar` + `SYSTEM_CALENDARS_REGISTER` + `HolidaysPlugin` (`Hooks.php:83-87`, `src/ChurchCRM/dto/SystemCalendars.php:42`, `src/plugins/core/holidays/src/HolidaysPlugin.php:36-41`), with an explicit registry call instead of a filter (A20).

**Why the Giving domain is core, not a plugin (revising #8180):** most of this work is needed with no gateway at all: per-person attribution (#9313), statements, deposit and report fixes, the audit log and the admin editor. The flow writes core tables (`pledge_plg`, `deposit_dep`) inside core transactions, and the portal and finance pages that call it are core. #8180's on/off property is kept: nothing online shows unless `bEnableOnlineGiving` is on and a gateway plugin is active.

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

- Each gateway plugin's `boot()` calls `PaymentGatewayRegistry::register($this, $this->gateway())`. `boot()` runs before `PluginManager` stores the instance (`loadPlugin()`, `src/ChurchCRM/Plugin/PluginManager.php:190-192`), so `register()` only records the entry. `forGiving()` and `get()` verify entries lazily, after loading ends, and drop (logged, red on readiness):
  - an owner that is not the loaded instance (`PluginManager::getPlugin()`, `:538-541`);
  - `getGatewayId() !== $owner->getId()`;
  - a class outside the owner's `mainClass` namespace;
  - an owner neither core nor approved with `payments.process` (new in `KNOWN_PERMISSIONS`, `src/ChurchCRM/Plugin/ApprovedPluginRegistry.php:40-57`), except `fake-gateway` under `CHURCHCRM_GIVING_SANDBOX=1`;
  - a taken id.
- **"Core" is decided by path:** the realpath of `PluginMetadata::getPath()` must lie under `src/plugins/core/`. The `plugin.json` `type` is self-declared (`src/ChurchCRM/Plugin/PluginMetadata.php:37`) and trusted by `getVerificationStatus()` (`PluginManager.php:359`); only URL installs check it (`src/ChurchCRM/Plugin/PluginInstaller.php:775-777`); `AbstractPlugin::getType()` returns 'community' for every plugin (`src/ChurchCRM/Plugin/AbstractPlugin.php:188-191`). `discoverPlugins()` scans community after core and keys by id (`PluginManager.php:75,94`), so a community plugin with id `stripe` would replace core Stripe; GIV-10 makes `discoverPlugins()` and `PluginInstaller` refuse a community id equal to a core id.
- Entries are bound to owners, so load order is irrelevant.
- **`FakeGateway` in CI:** `discoverPlugins()` scans only `src/plugins/core` and `src/plugins/community` (`PluginManager.php:75-84`), so CI setup copies `cypress/fixtures/plugins/fake-gateway/` into `src/plugins/community/fake-gateway/` (git-ignored) and the test seed activates it.
- `forGiving()` returns gateways that are ready, accept new gifts and support `sGivingCurrency`, ordered by `sGivingGatewayOrder`. Calls into each gateway are wrapped in try/catch, so a broken one drops out; the registry does not rely on `HookManager::applyFilters()` swallowing errors (`src/ChurchCRM/Plugin/Hook/HookManager.php:194-223`, try/catch `:209-216`).
- **Release gate (D1):** `GivingFeature::isAvailable()` hides every online-giving surface until GIV-20–24 are merged (the last gateway PR flips it; the sandbox flag bypasses it in CI).

| Hook (`src/ChurchCRM/Plugin/Hooks.php`) | Value | Fires |
|---|---|---|
| `GIFT_RECORDED` | `giving.gift.recorded` | once per new payment group from `PledgeWriter`, after commit |
| `GIFT_REFUNDED` | `giving.gift.refunded` | refund recorded |
| `RECURRING_GIFT_CREATED`/`_CANCELLED`/`_FAILED` | `giving.recurring.*` | lifecycle |
| `NEWSLETTER_OPTIN` | `newsletter.optin` | only after the gift succeeds |
| `DEPOSIT_CLOSED` (`:70`) | `deposit.closed` | wired into `POST /api/deposits/{id}` on 0→1; today only in dead `setDeposit` (`FinancialService.php:120-122`) |
| `DONATION_RECEIVED` (`:64`) | `donation.received` | unchanged: per row, again on edits (`FinancialService.php:438`) |

Payloads never carry secrets. Listeners receiving donor data need `hooks.financial`.

### 2.5 Webhook route in the plugin's folder

**Why a change is needed**
- All `/plugins` routes run behind `AuthMiddleware` (`src/plugins/index.php:114`); an unmatched route answers 302 (`:90-95`).
- `src/plugins/index.php:16` requires `LoadConfigs.php` → `Bootstrapper::init()` (`src/Include/LoadConfigs.php:54`), which may redirect via `checkAllowedURL()` under `bLockURL` (`src/ChurchCRM/Bootstrapper.php:112`), always starts a session with private cache headers (`:119`, `:487-489`), and **auto-upgrades the DB when code is newer** (`:130-168`).
- `.agents/skills/churchcrm/plugin-development.md:296-298` forbids public POST routes.

**Changes (GIV-12)**
1. `plugin.json` key `"webhookRoutesFile": "routes/webhook.php"`, parsed by `PluginMetadata`.
2. The first lines of `src/plugins/index.php` match `/plugins/([a-z0-9-]{2,20})/webhook$`; on a match they select a `Bootstrapper` **webhook boot mode**, require `LoadConfigs.php`, run `PluginWebhookApp`, return.
3. Webhook boot mode skips the `checkAllowedURL()` redirect, `initSession()`, `configureUserEnvironment()` and the auto-upgrade. While `Bootstrapper::isDBCurrent()` (`:642`) is false the app answers **503**; the next normal page load upgrades.
4. `PluginWebhookApp`: no Auth/CSRF/Cors/ChurchInfo/Version middleware, session or cookies; `Cache-Control: no-store`; POST only (405), body ≤ 1 MB (413), high per-IP ceiling; generic JSON errors, never a 302. It loads `webhookRoutesFile` only for an active, registered gateway, giving it a pre-guarded `$webhook` group that may register only `POST ''` (stock: `$webhook->post('', fn($q,$s) => (new WebhookProcessor())->handle($q,$s,$gateway))`); a `routesFile` route at `/{id}/webhook` is unreachable. Logs gateway, event id and outcome, never the body.
5. Body parsing is not a reason for the separate app: Slim's `BodyParsingMiddleware` adds a parsed copy and `Stream::__toString()` rewinds, so raw-body HMAC works either way.
6. Unloaded plugin that still owns `giving_*` rows → **503** + admin alert (gateways retry ~3 days); else JSON 404.
7. Lifecycle guard: optional `AbstractPlugin::canDeactivate(): ?string`, checked by `PluginManager::disablePlugin()` (`:483-515`) and uninstall. Gateways refuse while `grec_Status IN ('pending','active','past_due','review')` (pending gifts expire within 24 h, F14); the UI offers "Switch to receive-only" or "Cancel all N at the gateway and email donors" (audited, TOTP).
8. Webhooks never reach `AuthMiddleware`, so its public-path exemption (inline `/api/public` check upstream, `src/ChurchCRM/Slim/Middleware/AuthMiddleware.php:25-27`; `isPublicPath()` [portal branch]) is not widened by GIV-12. GIV-33 later adds exactly the `/portal/give` segment (F2).
9. `plugin-development.md:296-298` changes to: public POST only through `webhookRoutesFile` on a registered gateway, with raw-body signature verification and re-fetch.
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
| | no metadata (disputes, dashboard refunds, payouts): resolved through the parent charge, capture or payout to a local `gtxn`/`grec`; no match (shared accounts are normal) | 200 | `ignored` if unmatched |
| 5 `GivingRecorder::apply()`, one transaction per event | recorded | 200 | `processed` |
| 6 | permanent problem (unknown intent with our install id, amount mismatch, crypto edge cases) | 200 | `review` |
| | transient problem (timeout, deadlock, SQLSTATE 42S02) | 503 | `failed` |

- **Telemetry:** the app logger sends Warning and above to PostHog (`PostHogLogHandler`, `src/ChurchCRM/Utils/LoggerUtils.php:182`) with a synchronous 1 s POST (`src/ChurchCRM/Service/TelemetryService.php:97-125`), and core code on the webhook path logs there (for example a failing hook listener, `src/ChurchCRM/Plugin/Hook/HookManager.php:212`). Webhook boot mode builds the app logger without `PostHogLogHandler`, so no telemetry call runs inside the 10 s budget. The `giving` channel never goes to PostHog.
- Stripe endpoints omit `invoice.created` (a failing subscriber delays renewals up to 72 h).

### 2.6 Plugin layout and packaging

```
src/plugins/core/stripe/
  plugin.json  id "stripe", type core, minimumCRMVersion "7.8.0", mainClass ChurchCRM\Plugins\Stripe\StripePlugin,
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
- Stored keys are `plugin.{id}.{key}` (`PluginManager.php:774,789`) in `cfg_name varchar(50)` (`Install.sql:21`): the whole string must be ≤ 50 characters (`plugin.paypal.plan.live.annually.USD` is 36).
- **Plugin-local vendor (GIV-09):** `PluginManager::loadPlugin()` requires `{plugin}/vendor/autoload.php` if present, before the PSR-4 autoloader (`:202-229`). `composer:install` (`package.json:35`) also installs each `src/plugins/core/*/composer.json` (`--no-dev`), and `build:signatures` moves after it (`build` runs PHP and frontend in parallel today, `package.json:25-26`) so `signatures.json` covers plugin vendor. CI fails if a plugin lock duplicates a core or other-plugin package. One Dependabot `composer` entry per plugin. New `src/plugins/core/.htaccess` mirrors `community/.htaccess` (deny direct PHP); Caddy already blocks both (`docker/examples/frankenphp/Caddyfile:63-64`).

---

## 3. Data model

**Migrations:** `src/mysql/upgrade/7.8.0-giving-*.sql`, `7.8.0-finance-audit.sql`, `7.8.0-system-ratelimit.sql`; mirrored by hand in `Install.sql`, `orm/schema.xml`, `cypress/data/seed.sql`. Registered in `upgrade.json` only when the maintainer opens the 7.8.0 block (`current` is `dbVersion 7.7.1`, `scripts: []`). No column-level `IF NOT EXISTS`.

### 3.1 Changes to existing tables

| Table | Change | Migration / issue |
|---|---|---|
| `pledge_plg` | Data `plg_FamID = 0 → NULL`; anonymous = both ids NULL | `7.8.0-giving-anonymous-normalize.sql` / GIV-02 |
| `pledge_plg` | `plg_PerID mediumint(9) unsigned NULL` + index; `idx_plg_GroupKey`; no DB FK (stock style), nulled in code on person delete | `7.8.0-giving-person-attribution.sql` / GIV-05 |
| `pledge_plg` | `plg_gtxn_ID int NULL` + index; `plg_method` + 'CRYPTO'; `plg_schedule` + 'Annually' | `7.8.0-giving-columns.sql` / GIV-14 |
| `pledge_plg` | `plg_fr_ID mediumint(9) unsigned NULL` + index (fundraiser); `plg_pn_ID mediumint(9) unsigned NULL` + index (paddle, `paddlenum_pn.pn_ID`, `Install.sql:990`) | `7.8.0-giving-columns.sql` / GIV-14 (the recorder writes them; GIV-50 uses them) |
| `pledge_plg`, `deposit_dep` | utf8 → utf8mb4 (`Install.sql:40,579`), as `7.7.0-events-utf8mb4.sql` did | `7.8.0-giving-utf8mb4.sql` / GIV-14 |
| `deposit_dep` | `dep_Type` + 'Online'; `dep_Gateway varchar(32) NULL`; `dep_PayoutRef varchar(191) NULL`, UNIQUE(gateway, ref); `dep_FeeAmount decimal(10,2) DEFAULT 0`; `dep_NetAmount decimal(10,2) NULL`; `dep_ReconStatus enum('none','balanced','discrepancy')`; INDEX(gateway, closed, date) | GIV-14 |
| `donationfund_fun` | `fun_OnlineGiving tinyint(1) NOT NULL DEFAULT 0` (opt-in, so restricted funds are never offered by accident) | GIV-14 |

**Propel.** The existing `plg_EditedBy` FK (`orm/schema.xml:544-546`) is unnamed; a second FK to `person_per` would rename generated methods to `…RelatedBy…`. GIV-05 names both: existing `phpName="Person"`, `refPhpName="Pledge"` (callers `FinancialService.php:79`, `src/api/routes/finance/finance-payments.php:136`, `PledgeQuery.php:59,134` unchanged); new `phpName="Donor"`, `refPhpName="DonatedPledge"`.

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
| `grec_Currency`, `grec_Net`, `grec_CoveredFee`, `grec_Gross`, `grec_Allocation` | decimal(10,2); JSON `[{fundId,amount}]` |
| `grec_Frequency`, `grec_DayOfMonth`, `grec_Weekday`, `grec_MonthOfYear`, `grec_FirstChargeDate` | enum('weekly','monthly','annually') DEFAULT 'monthly'; 1–28, 31 = last day |
| `grec_Status` | enum('pending','active','past_due','cancelled','expired','review'); exits in F3.5–3.6, F5, F7, F14 |
| `grec_MethodLabel`, `grec_LastChargedAt`, `grec_LastChargedAmount`, `grec_NextChargeAt`, `grec_LastSyncedAt`, `grec_FailureCount` | |
| `grec_Created`, `grec_CreatedBy`, `grec_CancelRequestedAt`, `grec_CancelRequestedBy`, `grec_CancelledAt`, `grec_CancelledBy` | signed mediumint for actor ids |
| `grec_CancelSource` | enum('donor','staff','gateway','plugin_disable') |

**Others**
- `giving_customer_gcus`: gateway, mode, customer ref, `gcus_per_ID` (NULL once the person is deleted); UQ(gateway, mode, ref) and UQ(gateway, mode, per_ID).
- `giving_webhook_event_gwhe`: `gwhe_ID bigint`, gateway, mode, `gwhe_EventRef` (UQ with gateway), `gwhe_Source` (webhook, reconcile, return), type, object ref, `gwhe_Status` (received, held, processed, ignored, failed, review), attempts, sanitised last error, payload hash, `gwhe_Payload` (encrypted, 90 days), timestamps.
- `giving_notification_gntf` (outbox): `gntf_Type` (receipt, refund, recurring_created/cancelled/failed, fundraiser_receipt, admin_alert), `gntf_DedupeKey varchar(190) UQ` (`receipt:{gtxn}`), to-email, per/fam/gtxn/grec ids, scheduled-for, `gntf_Status` (pending, sent, failed, skipped), attempts, last error, sent-at. Standalone (Volunteer v2's `vntf` is similar [portal branch]).
- `giving_consent_gcon`: `gcon_gint_ID`, email, name, `gcon_DefaultState` (checked, unchecked), form version, wording, IP hash, `gcon_Status` (held, dispatched, stored, discarded, withdrawn), timestamps.
- `giving_noncash_gnc` (1:1 with a crypto charge `gtxn`, shared by all its rows): `gnc_gtxn_ID` PK/FK, `gnc_Asset`, `gnc_Quantity decimal(24,12)`, `gnc_Network enum('onchain','lightning')`, `gnc_Rate decimal(20,8)`, `gnc_RateSource`, `gnc_TxIds`, `gnc_AppraisalFlag` (> $5,000, Form 8283 Section B), `gnc_DisposedDate`, `gnc_DisposalProceeds`, `gnc_Form8282FiledDate`.
- `finance_audit_faud` (append-only; no update/delete API): `faud_ID bigint`, date, `faud_ActorPerId` (signed), `faud_ImpersonatorPerId`, `faud_Action`, entity type and id, `faud_Before`/`faud_After` JSON (secrets excluded), `faud_IpHash`.
- `system_ratelimit_srl`: `srl_Key varchar(191) PK` (hashed bucket), window start, count; one atomic UPDATE per hit.

### 3.3 Invariants (enforced in code; a nightly check of all but I5 raises `admin_alert`)
- **I1** Σ `plg_amount` for a succeeded charge's rows = `gtxn_Gross`.
- **I2** Rows are written once, in the transaction that marks the charge succeeded, under `SELECT … FOR UPDATE`, on the Propel write connection only.
- **I3** Σ refunds and dispute debits ≤ gross + dispute credits.
- **I4** Gateway rows never change amount, date, method or fund split and are never deleted, except by the test purge (F13). Only two updates are allowed: `plg_FamID`/`plg_PerID` through the attribution service (F8), and one `plg_depID` NULL → payout deposit attach by `GivingDepositService` (F13); both audited, in place.
- **I5** (write-time rule, not in the nightly check: ChurchCRM keeps no family-membership history) When a row credited to a person is written, `plg_FamID` = `PledgeWriter::familyOf(person)`, NULL if the person has no family. Afterwards it changes only through the attribution service: re-attribution, the D11 move, or the family donation move (F8), each audited. Enforced in `PledgeWriter` and the attribution service.
- **I6** Anonymous = both ids NULL; `plg_FamID` is never 0.
- **I7** A payout deposit is `balanced` when Σ rows − `dep_FeeAmount` = `dep_NetAmount`, with `dep_FeeAmount` = every fee balance transaction in the payout, including Stripe's separate `stripe_fee` (Billing) lines.
- **I8** Recurring gifts are cancelled locally only after the gateway confirms, or says already cancelled or not found.
- **I9** No test-mode rows in live books after go-live.
- **I10** Every CRYPTO row's `plg_gtxn_ID` (for a refund, its `gtxn_Parent_ID`) has a `gnc` row; cash totals exclude CRYPTO.
- **I11** Report totals = ledger totals, refunds included.

### 3.4 Settings (category-less core `ConfigItem`s on `/admin/online-giving`)

| Key | Default | Meaning |
|---|---|---|
| `bEnableOnlineGiving`, `bEnablePublicGiving`, `bPortalShowGiving` | 0, 0, 0 | master (plus D1 gate); public page; portal section, set on Admin → Member Portal [portal branch] and added by GIV-30 |
| `bFinanceShowGivenBy` | 1 | admin-entry "Given by" UI (#9313's "optional"); data unaffected |
| `sGivingCurrency` | USD | ISO 4217, 2-decimal; replaces hard-coded `<CURDEF>USD` in OFX (`src/ChurchCRM/model/ChurchCRM/Deposit.php:65`) |
| `sGivingGatewayOrder`, `sGivingDepositBatching`, `aGivingAmountPresets` | stripe,paypal,btcpay; weekly; 25,50,100,250 | batching: daily, weekly, monthly |
| `iGivingMinPublic`, `iGivingMaxPublic`, `iGivingMinMember`, `iGivingMaxMember` | 5, 10,000; 1, 25,000 | whole currency units; hard cap 999,999.99 |
| `sGivingCoverFeeDefault`, `iGivingFeeCoverFund` | unchecked, 0 | 0 = spread |
| `sGivingNewsletterOptIn` | checked (D4) | checked, unchecked, off; help names GDPR and CASL |
| `sGivingReceiptText`, `bGivingReceiptPerRecurringCharge`, `sGivingStatementMode` | IRS wording; 1; household | mode: household, individual, both |
| `sGivingTrustedProxies` | empty | CIDRs for `ClientIp` |
| `sGivingPrivacyNoticeUrl`, `sGivingRefundPolicyUrl` | empty | shown on giving pages (GIV-71) |
| `iGivingRefundAlertAmount`, `iGivingWebhookRetentionDays`, `iGivingPublicCircuitBreaker` | 500, 90, 20 | alert threshold; days; failed/abandoned public intents per hour (`processing` ones do not count) |
| `sGivingInstallId` | set on first save | install UUID (A29) |

The HMAC key for IP hashes and return tokens is not a setting. It is derived from the secrets key outside the DB (A15): `hash_hkdf('sha256', <secrets key>, 32, 'giving-hmac')` (GIV-13). A key stored in `config_cfg` would sit in every dump, and IPv4 HMACs can be brute-forced (2^32) with it. Without a secrets key giving runs in test mode only, with a random test key kept in the DB and dropped at go-live. Rotating the secrets key invalidates open return tokens (the page then shows "Thank you").

---

## 4. Flows

**F0. Recording (`GivingRecorder::recordCharge()`).** Every path ends here, on `Propel::getWriteConnection()` only; nothing uses the mysqli connection behind `FunctionsUtils::genGroupKey()`/`runQuery()` (`src/ChurchCRM/Utils/FunctionsUtils.php:108-125`).
1. `BEGIN`; upsert `gtxn` by (gateway, mode, kind, ref); `SELECT … FOR UPDATE`; already succeeded → commit, return.
2. Resolve the owner by intent UUID or subscription ref. Stripe sessions set `payment_intent_data.metadata`, `subscription_data.metadata` and `client_reference_id`, so PaymentIntent, invoice and subscription objects carry the ids.
3. Check the `fetchPayment()` snapshot: succeeded, same currency, amount = `gint_Gross`/`grec_Gross`; else `review`, no rows. A one-fund recurring charge records the actual amount; several funds are scaled and flagged. A BTCPay overpayment goes to review with no rows (F11).
4. `AllocationSplitter` splits the gross: covered fee in proportion, by largest remainder (A12).
5. `GivingDepositService::depositFor()` under `GET_LOCK('giving_deposit_'.gw)` on the same connection (batch); payout mode leaves `plg_depID` NULL.
6. `PledgeWriter::insertGroup()`, one Payment row per fund: date = `gtxn_OccurredAt` in `sTimeZone`; FY via `FiscalYearUtils::getFiscalYearIdForDate()`; ids per F8 (recurring: `familyOf(owner)` at charge time, D11); method per §3.1; `plg_gtxn_ID`, `plg_fr_ID`, `plg_pn_ID` (from `gint_fr_ID`/`gint_pn_ID`); NonDeductible = fundraiser FMV part or 0; schedule: one-time → 'Once', weekly/monthly/annually → 'Weekly'/'Monthly'/'Annually'; `plg_aut_Cleared=1`; EditedBy = actor or −3; GroupKey `gtxn{id}|0|{fam}|{funds}|{date}` (no scan).
7. Update `gtxn` (succeeded, GroupKey, deposit, fee), `gint` (completed), `grec` (last/next charge; active unless in review); audit; enqueue `receipt:{gtxn}` (an INSERT into `gntf`, status pending); GIV-34 adds: move a `held` consent to dispatch; `COMMIT`.
8. After commit: `DONATION_RECEIVED` per row, `GIFT_RECORDED` once; GIV-17 flushes up to 5 notifications.

**Family lookup.** Every place that copies "the person's family" into `plg_FamID`, `gint_fam_ID`, `gtxn_fam_ID` or `grec_fam_ID` calls `PledgeWriter::familyOf(Person)` (GIV-04). It maps `per_fam_ID = 0` to NULL: the column is `NOT NULL default '0'` (`Install.sql:521`), and unlinking members sets 0 (`src/api/routes/people/people-family.php:501`). Without it, family-less people would write 0 and break I6.

**Install identity (A29).** `sGivingInstallId` is created when giving is first configured (so test gifts carry it) and fixed at go-live, with a fingerprint of `$URL` and DB name. When the fingerprint changes, the admin page asks "Moved this install" (keep id) or "This is a copy" (new id, test mode, keys re-entered); live mode is off until answered. Webhooks that arrive meanwhile are verified and stored as `gwhe` `held` (§2.5); "Moved this install" processes them, "This is a copy" marks them ignored. A 200-ignored live event would never be resent.

**F1. Portal gift, one-time** [portal branch, #8977]
1. `GET /portal/giving` with the actor from `PortalAccessMiddleware` (`src/ChurchCRM/Portal/PortalAccessMiddleware.php:50-58` [portal branch]): funds with `fun_Active='true' AND fun_OnlineGiving=1` in `fun_Order`, `forGiving()` capabilities and fee models, recurring notices, fundraiser balances.
2. Fund lines (up to 5, or presets), gateway and method, optional fee cover; the HoH also sees "Give as: Me / Our household".
3. `POST /api/portal/giving/intents` (`Idempotency-Key`; `CSRFMiddleware`, `PortalApiMiddleware` [portal branch], giving no-masquerade middleware §5.1). No person or family ids accepted. The server validates funds and limits, recomputes the fee (> 1¢ difference → 409 "Total changed, please review"), creates `gint` (per = actor, fam = `familyOf(actor)`), calls `beginCheckout()`, checks the URL's host against `getRedirectHosts()`, returns `{redirectUrl}`. For `kind=recurring` it answers **422** when the gateway lacks `supportsRecurring()`, the frequency is not in `frequencies`, or day = last and the gateway lacks `monthEnd`, so a crafted POST cannot create a `grec` the gateway cannot honour.
4. The `portal-giving` bundle re-checks the host, then `location.assign()`. No form post: `form-action 'self'` (`src/Include/Header-Security.php:32-46`) covers redirect targets.
5. Return: `GET /portal/giving/return?intent={uuid}` (top-level GET keeps the Lax cookie); `gint_per_ID` must equal the actor (else 404); `completeReturn()` → F0; shows succeeded with receipt, pending (ACH, PayPal PENDING, on-chain; polls `GET /api/portal/giving/intents/{uuid}`), or failed.
6. Webhook before or after the return: F0 makes order irrelevant.

**F2. Public anonymous gift (D4)** [#8977]
1. **`GET /portal/give[?fund=&amount=]`**, outside `PortalAccessMiddleware`. The AuthMiddleware public-path list gains exactly `/portal/give`, matched on whole segments (`isPublicPath()`, `AuthMiddleware.php:138-151` [portal branch]), so `/portal/giving` stays private. `PublicGivingEnabledMiddleware` answers 404 when `bEnablePublicGiving` is off, except for a request carrying a valid `fundraiserPayment` token (the page and its `POST /api/public/giving/intents`), so fundraiser pay links (F12) work without a public page; the D1 gate and `bEnableOnlineGiving` still apply.
   - **Anonymous-safe Twig:** `PortalExtension::getGlobals()` always builds `member` (`:262`), `nav` (`PortalNav.php:167`) and color mode (`:210`), and each calls `AuthenticationManager::getCurrentUser()`, which throws without a session provider (`src/ChurchCRM/Portal/PortalExtension.php:208-216,260-266`; `AuthenticationManager.php:24-35,42-56` [portal branch]). GIV-33 adds `AuthenticationManager::tryGetCurrentUser(): ?User` and makes these globals return null or defaults. The route calls `PortalTwig::preparePage()` (`PortalTwig.php:56-63` [portal branch]) for the CSP nonce.
   - `giving/public.html.twig` extends `layout-public.html.twig` (logo, no nav or account menu); both overridable (D8). CSRF token from the anonymous session.
2. **Form:** fund, amount, gateway/method, fee cover; optional "Email me a receipt" (prompt at $250+); "Join our newsletter" once an email is entered (`sGivingNewsletterOptIn`, GIV-34); honeypot, minimum fill time; privacy and refund links; **"If you are a member, click here"** → `/portal/giving?fund=&amount=`; "No login yet? Contact the church office"; "Members can sign in to give monthly"; signed-in visitors see "give from your account". The member link and "sign in to give monthly" render only when the portal Give tab is available (portal on, `bEnabledFinance`, `bPortalShowGiving`, the D1 gate, `bEnableOnlineGiving`, a gateway); otherwise the page shows only "Contact the church office", so members never log in to a hidden section.
3. **`POST /api/public/giving/intents`** behind `StrictCSRFMiddleware` (rejects the `X-API-Key` bypass, `src/ChurchCRM/Slim/Middleware/CSRFMiddleware.php:38-44`), `RateLimitMiddleware` and the circuit breaker. Creates `gint` (channel public, ids NULL, email); GIV-34 adds a `held` `gcon` if the newsletter box is ticked. Always rejects `kind=recurring` (422). Stripe gets `customer_email` as prefill only, not `receipt_email`.
4. **Return** carries an HMAC token (uuid + 7-day expiry, giving HMAC key, §3.4): `GET /portal/give/return?t=…` needs no session. Valid → status, amount, fund, printable receipt. Invalid/expired → "Thank you. If you entered an email, your receipt is on its way." Never a 404 after paying.
5. **F0 writes** both ids NULL, EditedBy −3; receipt to the email if given. With GIV-34: `gcon` → dispatched and `NEWSLETTER_OPTIN` (→ `stored` if unhandled); a failed gift → `gcon` discarded.
6. **Member link** works on upstream as is: `AuthMiddleware::redirectToLogin()` stores path and query in `$_SESSION['location']` (`AuthMiddleware.php:197-215`, write `:208`, called `:109`); `AuthenticationManager::authenticate()` returns there (`AuthenticationManager.php:136-149`), after any 2FA step (`:131-134`). `/portal/giving` must stay behind the global `AuthMiddleware`, which runs before `PortalAccessMiddleware` (`src/ChurchCRM/Slim/MvcAppFactory.php:61-67`; `src/portal/index.php:34-42` [portal branch]), whose own redirect stores no location (`PortalAccessMiddleware.php:39-43` [portal branch]).
7. Never auto-matched to a person, including through the email log (§6). No recurring on this page.

**F3. Recurring setup** (portal only)
1. The toggle shows only when `supportsRecurring()` (and `walletRecurring` for wallets). BTCPay: "Bitcoin gifts can't repeat automatically."
2. Defaults: Monthly on today's day (29–31 → "Last day"; on a gateway without `monthEnd`, such as PayPal until Q9 is settled, 29–31 → the 1st, so the first gift is on the 1st of next month); Weekly on today's weekday; Annually on today's date. Choices: days 1–28 and "Last day" (if `monthEnd`). `RecurrenceScheduler` applies the same fallback, so the date and the sentence agree.
3. `RecurrenceScheduler::plan()` returns the first-charge date (next occurrence of the chosen day; today only if today is that day), the anchor and the sentence ("First gift on Oct 1, then monthly on the 1st"). Anchors are 12:00 `sTimeZone` in UTC, so DST never moves the date.

   | | Stripe Checkout `mode=subscription` | PayPal Subscriptions v1 |
   |---|---|---|
   | Chosen day is today | no anchor; charged at checkout | no `start_time` (now) |
   | Monthly 1–28 | `subscription_data.billing_cycle_anchor` = next occurrence, `proration_behavior=none` | `start_time`; interval MONTH |
   | Monthly last day | `subscription_data.billing_cycle_anchor_config.day_of_month=31` (bills on the last day of short months) | hidden until verified (Q9) |
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
- **Record:** `charge.refunded`/`refund.updated`, `PAYMENT.CAPTURE.REFUNDED`, `PAYMENT.SALE.REFUNDED` → **negative** rows in proportion to the charge's rows (its immutable allocation, I4), NonDeductible included; same ids; refund date; open deposit (payout mode: NULL until absorbed); "Refund of {ReceiptNo}"; cross-year → re-issue flag; `GIFT_REFUNDED`; email. Dashboard refunds take this path (audited as external). Money returns only to the original method.
- **BTCPay:** pull payment; claim link stored; staff **Record refund** after payout (only manual money write; audited, TOTP).
- **Disputes:** `charge.dispute.created`/`CUSTOMER.DISPUTE.CREATED` → disputed + alert, `grec` to review · `funds_withdrawn`/`PAYMENT.CAPTURE.REVERSED` → `dispute_debit`, negative rows, fee into `gtxn_Fee` · `funds_reinstated` → `dispute_credit`, positive rows · `closed` → status. A `grec` in review keeps recording charges; staff either **Resume** it (after `getRecurring()` confirms it is live → active) or cancel it (TOTP, → cancelled). Review blocks plugin disable (§2.5 item 7).

**F8. Admin entry** (`src/finance/views/pledges/editor.php`)
- **Given by** (when `bFinanceShowGivenBy`): Family (existing TomSelect on `/api/families/search`, `:85-93, 368-390`, plus "Credit to: Whole household | {member}"), Person (family filled from the person), Anonymous. Hint: "Only the head of household and the credited person see this gift in the Member Portal."
- The payload (`:536-547`) adds `PersonID`. The rule and the 999,999.99 cap live in `PledgeWriter`, so all three write routes share them: `POST /api/payments/` (`finance-payments.php:55-63`), `POST /api/payments/pledges` (`:246`) and `PUT /api/payments/{groupKey}` (`:321`). Person → PerID P, FamID = `familyOf(P)`; Family → NULL, F; Anonymous → NULL, NULL. Both sent and disagreeing → 400; no `PersonID` → as today.
- **Gateway groups** are read-only: `PUT` → 409 "Online gifts can only be re-attributed"; `DELETE` → 409 "Refund it through the gateway".
- **Attribution service** (GIV-05) is the only path that changes ids on existing rows. It updates in place on every row of a group and on its `gtxn` (`gtxn_per_ID`/`gtxn_fam_ID`), writes one `faud` row per group, and leaves `plg_EditedBy` alone (so −3 survives). It serves `PATCH /api/payments/{groupKey}/attribution` (Finance, office and gateway groups), the D11 move and the family donation move.
- **Family donation move:** the family-delete page (`src/SelectDelete.php:126-146`) calls `POST /api/family/{id}/donations/move` (`src/api/routes/people/people-family.php:538-572`). Today it rewrites `plg_FamID` on every row of the family, all years, sets `plg_EditedBy` to the current user, and writes no audit. GIV-05 reimplements it on the attribution service. It is refused while the family owns a pending, active, past_due or review `grec`. Person-credited rows move too, listed in the audit rows, because the source family is being merged away and would otherwise drop them from household statements. All years move: this is a family merge, not the D11 case.
- **Family change (D11):** when `per_fam_ID` changes (`src/PersonEditor.php`, `src/FamilyEditor.php`, `src/ConvertIndividualToFamily.php`, `src/api/routes/people/people-family.php`), the result offers "Move this year's gifts to the new family": rows with PerID = person, FamID = old family, current calendar year, via the attribution service. Earlier years never move.
- Rebase on #10021 (Ctrl+Enter, open PR) if it merges first.

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
- **Balance:** `FundraiserBalanceService::forPaddle(pnId)` = Σ `di_sellprice` won + Σ `mb_count × di_sellprice` − Σ `plg_amount` with `plg_pn_ID` = that paddle; FMV = matching Σ `di_estprice`. Household view sums the family's paddles. Keying on the paddle, not on `plg_PerID`, means a payment entered as family-level (the only mode when `bFinanceShowGivenBy` = 0) or paid by the HoH still lowers the right balance.
- **Paying:** portal card "Spring Auction: you owe $180 [Pay now]" (own paddle; the HoH sees one card per household paddle, each with its own Pay now, so one payment covers one paddle). Staff "Send payment link" (Manage Fundraisers, `User.php:189`, or Finance): `Token` type `fundraiserPayment` with `reference_id` = `paddlenum_pn.pn_ID` (fixes fundraiser and buyer); `Token::build()` (`src/ChurchCRM/model/ChurchCRM/Token.php:21-35`) sets 30 days, 5 uses. It opens the public page locked (fund `fr_fund_ID`, amount = balance or less, no recurring, "Paying for {name}"), attributed to the buyer, and works even when the public page is off (F2.1). The buyer statement's "Credit card __ Exp __" line (`src/fundraiser/routes/reports.php:602`) becomes "Pay online" + QR.
- **Recording:** `plg_fr_ID` and `plg_pn_ID` from `gint_fr_ID`/`gint_pn_ID` (set from the token or the portal card); `plg_NonDeductible = min(payment, FMV not yet covered)`; over $75 the receipt has the quid-pro-quo statement. Donation-style fundraisers use `?fundraiser={id}` (no paddle).
- **Admin:** editor Fundraiser select for cash/check; when a fundraiser is chosen a Paddle picker is required and sets `plg_pn_ID`, and "Given by" defaults to the paddle's buyer; **Collected** beside **Raised**, which stays sell-price based (`getViewModel()`, `src/ChurchCRM/Service/FundRaiserService.php:139-168`, sum `:161-163`; list page `getListSummaries()`, `:52-73`).

**F13. Deposits**
- **Batch (v1):** under `GET_LOCK`, find the open `dep_Type='Online'` deposit for the gateway and period (`sGivingDepositBatching`, `sTimeZone`) or create "{Gateway} online gifts, week of {date}", `dep_EnteredBy` NULL. A closed period's gift goes to a new open deposit (also late ACH and refunds). Test mode uses "[TEST] …" deposits.
- **Payout (Stripe, GIV-42):** rows wait with `plg_depID` NULL; `payout.reconciliation_completed` → `listPayoutLines()` → deposit on UQ(gateway, payout ref), date = arrival; attach only NULL-deposit rows (else discrepancy); set fee, net, recon status (I7).
- Closing fires `DEPOSIT_CLOSED`. `Deposit::preDelete` (`Deposit.php:29-34`, which deletes all pledges today) refuses closed deposits (GIV-01) and gateway or gateway-row deposits (GIV-16). Gateway deposit type is fixed; reopening is audited; staff cannot create 'Online' deposits (`finance-deposits.php:50`).
- **Test purge (Go live, GIV-15):** `GivingTestPurgeService` runs in one transaction, audited. It deletes only `gtxn` with `gtxn_Mode = 'test'`, their pledge rows, `gint`, `grec`, `gnc` and `gntf` rows, and deposits that hold only test-mode rows (the "[TEST] …" deposits), closed or not. It bypasses the delete guards above and `Pledge::preDelete` (`Pledge.php:33-41`) on purpose; no other path does.
- The slip shows CRYPTO under "Non-cash property" at bookkeeping value, outside the cash total (D9).

**F14. Reconcile.** `GivingReconcileService` is a `SystemService::runTimerJobs()` step (`src/ChurchCRM/Service/SystemService.php:105-131`), a **Sync now** action, and a 2-call step after each webhook. Per run, ≤ 20 API calls:
1. Intents: `created` > 24 h → expired. `redirected` > 2 h with no processing event → `completeReturn()` → completed, processing or abandoned. `processing` intents wait for the gateway (never abandoned, not counted by the circuit breaker, not purged). When a recurring `gint` becomes abandoned or expired, its `pending` `grec` → expired (F3.6).
2. Re-fetch pending `gtxn`.
3. Catch up with `gwhe_Source=reconcile`: Stripe `GET /v1/events` (30-day window), PayPal `GET /v1/billing/subscriptions/{id}/transactions` and `GET /v1/notifications/webhooks-events`, BTCPay invoice search by `orderId`.
4. Retry failed `gwhe` (< 10 attempts).
5. Refresh active `grec` daily.
6. Drain ≤ 50 notifications (GIV-17).
7. Check I1–I4 and I6–I11 (I5 is enforced at write time).
8. Purge per retention.

Cron is the supported scheduler (`src/cli/timerjobs.php:57`). Admin page loads are a fallback (`src/skin/js/Footer.js:178` → `src/api/routes/background.php:10,29-31`), rate-limited by `iTimerJobsMinIntervalMinutes` (`SystemConfig.php:276`); portal pages do not load `Footer.js`. Readiness reuses `sLastTimerJobsRunDateTime` (`SystemService.php:81`) and `iTimerJobsStaleHours` (`SystemConfig.php:275`, dashboard `src/admin/routes/dashboard.php:150-153`): stale cron blocks live mode.

---

## 5. UI and reports

### 5.1 Portal Giving [portal branch, #8977]
- **Nav:** `PortalNav::GIVING` "Giving", `fa-solid fa-hand-holding-heart`, between My Family and Profile (`PortalNav.php:33-38,45-107`). `isGivingVisible()` = `bEnabledFinance` (`SystemConfig.php:268`) + `bPortalShowGiving` + a person record. The Give tab also needs the D1 gate, `bEnableOnlineGiving` and a gateway; otherwise the section lands on History, which needs none. URL-per-tab with a `giving/partials/tabs.html.twig` partial like volunteer's. Portal home gets a Give card.
- **Routes:** `/portal/giving`, `/portal/giving/history[?year=]`, `/portal/giving/history/{year}/statement.pdf|.csv`, `/portal/giving/return`, `/portal/give`, `/portal/give/return`.
- **Templates:** `giving/{give,history,return,public,public-return}.html.twig`; partials `tabs, recurring-notice, more-info-dialog, receipt, fund-lines`; all overridable (D8), documented in `docs/portal-templates.md` [portal branch]. New `PortalExtension::getFilters()` `money` filter over `CurrencyFormatter` (`src/ChurchCRM/Utils/CurrencyFormatter.php`). Dates preformatted in PHP per `docs/portal-templates.md:378-379,495` [portal branch].
- **API** `src/api/routes/portal/portal-giving.php` [portal branch]: `quote, intents, intents/{uuid}, recurring/{uuid}, …/cancel, …/manage` behind `CSRFMiddleware` + `PortalApiMiddleware` (`PortalApiMiddleware.php:27-52`); no person or family ids (`portal-me.php:16-36` invariant). Writes use a giving no-masquerade middleware: 409 "Giving is not available while viewing as another person" (`NoActiveMasqueradeMiddleware`, `:21-41` [#9843], has a nested-masquerade message). Bundle `portal-giving` with `portal-forms.ts`, `portal-toast.ts`, native `<dialog>`.
- **Give tab order:** recurring notices; fundraiser balance card; presets and fund lines (+ Add another fund, ≤ 5; `fun_Description` help); Give as (HoH); gateway/method (hidden if one); recurring panel with live sentence; fee cover with `aria-live` estimate; button "Give $103.30" (one-time) or "Give $104.05 monthly" (recurring, F9); non-HoH note "Gifts you make are also visible to your head of household"; privacy and refund links; TEST banner.
- **Giving History (D3):** `GivingScope::forPortalActor()`: HoH (actor in `Family::getHeadPeople()`, list-aware, `src/ChurchCRM/model/ChurchCRM/Family.php:227-230,274-285`) → `plg_FamID = fam OR plg_PerID = actor`; others → `plg_PerID = actor`. Payments, online or office. Heading "Giving for the Smith household" or "Gifts credited to you — household gifts appear in the head of household's history". Calendar-year sections, newest first: total and per-fund subtotals; Date | Given by (HoH) | For | Method ("Online" badge) | Amount | Status; pending marked and excluded from totals; refunds negative; "Non-cash gifts" block without value or total.
- **Download:** "{year} statement" (PDF/CSV) is what `GivingStatementService` would mail that person under `sGivingStatementMode`; if the mode gives them none (non-HoH in Household) it is a "Giving summary (not a tax statement)". Current year is year-to-date.
- **Masquerade** [#9843]: reads only if the impersonator `isFinanceEnabled()` (`User.php:181`); writes 409; audited with `ImpersonationService::getImpersonatorUserId()` (`:54` [portal branch]).

### 5.2 Public page
See F2. Framing stays forbidden (`X-Frame-Options: SAMEORIGIN`; cross-site iframes also lose the Lax cookie), so churches link to it, with `?fund=`/`?amount=` deep links and a QR builder. The Caddy example needs a `/portal/*` handle if #8977 lacks one.

### 5.3 Admin, finance, statements
- **`/admin/online-giving`** (admin): settings; webhook URLs; plugin settings links; Go live (test purge, F13); QR builder; install-identity prompt.
  - **Readiness, blocking live:** HTTPS from the configured `$URL`, not `X-Forwarded-Proto` (trusted blindly at `Bootstrapper.php:470`); ISO currency matches `sCurrencySymbol` (`SystemConfig.php:178`) and the gateway account where exposed; ≥ 1 online fund; email enabled (`SystemConfig::isEmailEnabled()`, `:583`); fresh cron; secrets key outside the DB; DB current; install identity confirmed; keys match mode.
  - **Warnings:** plugin head/footer scripts on giving pages (D8); no webhook in 7 days; privacy or refund URL empty.
- **Admin → Member Portal:** `bPortalShowGiving` [portal branch] (GIV-30).
- **`/finance/online-giving`** (`FinanceRoleAuthMiddleware`; Finance menu `getDepositsMenu()`, `src/ChurchCRM/Config/Menu/Menu.php:282-310`, shown only when available). Built by GIV-40; later issues add their actions:
  - **KPIs:** 30-day gross, fees, net, count; active recurring count and monthly total.
  - **Transactions:** re-attribute; resend receipt (GIV-17); refund and erase guest PII (GIV-41).
  - **Recurring:** list; alerts for deceased or deactivated owners; staff cancel with TOTP, `CancelSource=staff`, donor emailed, and Resume from review (GIV-41).
  - **Needs review:** each reason has an action: Accept as received (records the received amount), or Refund (GIV-41).
  - **Undeposited.**
  - **Gateways:** health, mode, last webhook/sync, receive-only switch, Sync now.
  - **Newsletter sign-ups CSV** (GIV-34).
  - **Households:** families with gifts but no living, active head.
- Finance dashboard card; `/finance/funds` "Offer online" toggle; `person-view.php` Finance-only Giving card (`GET /api/payments/person/{id}/list`); family view Given by and Source columns (card `src/people/views/family-view.php:651-695`; columns are DataTable definitions in `src/skin/js/FamilyView.js:141-175`, fed at `:105`); `DepositSlipEditor.php` gross/fees/net; `PledgeDetails.php` "Online transaction" panel; paddle list Send payment link, Paid, Balance.

**Statements** (`GivingStatementService`, extracted from `src/Reports/TaxReport.php`; same service for portal downloads)
- **Household** (default): all rows with `plg_FamID` = family, with a Given by column; rows with FamID NULL and PerID set (no family) get individual letters.
- **Individual:** each person's PerID rows, addressed to them (`per_Envelope`); family-level rows (PerID NULL) go on the letter of the head with the lowest `per_ID` among `getHeadPeople()` (which returns heads in no set order), or on a household letter as today when the family has no head.
- **Both:** gifts credited to any non-HoH person, spouse included, go only on that person's statement; family-level and the HoH's own gifts on the household statement. Each gift on exactly one statement.
- Anonymous left out; crypto in a non-cash section without value; covered fees included; refunds netted; minimum amount applied to the donor total, not per row (per row today, `FinancialService.php:736`); NonDeductible unchanged (`TaxReport.php:263-269`).
- Build the missing view behind `/finance/reports/tax-statements` (`src/finance/routes/reports.php:31-46`; `reports/tax-statements.php` does not exist).
- **Head lists:** `src/FinancialReports.php:131-150` already splits `sDirRoleHead` (`:137-138`) but `intval()`s `sDirRoleSpouse` (`:139-140`) and joins spouses for "John & Jane" (`:145-150`); make the spouse role list-aware (`Family::getAdults()` semantics), not head-only. `src/FamilyEditor.php:481` casts `sDirRoleHead` to int; take the first id.

### 5.4 Reports
- "Anonymous" (gettext) replaces "Unassigned" (`TaxReport.php:87`; `src/Reports/AdvancedDeposit.php:109,312,480,647`).
- `FamilyPledgeSummaryService.php:26,38` and `FundContributorsService.php:66,78` get LEFT JOINs and drop `filterByAmount(0, GREATER_THAN)` so refunds net. Office entry stays positive-only (`FinancialService.php:298,390`, `editor.php:517`); `PledgeWriter` allows negative rows internally.
- Donor count: `getYtdDonorFamilyCount()` (`FinancialService.php:988-999`) already skips NULL via `COUNT(DISTINCT plg_FamID)` (`:996`); its only defect, anonymous rows stored as 0, goes with GIV-02. After GIV-05 it counts distinct family, or person when the family is NULL.
- Deposit PDF totals CREDITCARD, BANKDRAFT and CRYPTO (non-cash) besides CHECK and CASH (`Deposit.php:428-470`), plus an Online gross/fees/net summary. CSV adds person, source, gateway, external ref, fee. OFX uses `sGivingCurrency`.
- Advanced Deposit: CRYPTO label in all three method blocks (`AdvancedDeposit.php:371-379, 539-547, 707-715`); Given by, Source, Gateway filters.
- **New (GIV-43):** #9313's Contributions by Person, Fund Giving by Person, Top Contributors by Fund, Building Fund Progress by Member; online by gateway (gross, covered, fees, net); recurring forecast; past-due; disputes; non-cash register (8283/8282).
- Zero Givers, Reminder and Voting Members stay family-level (documented).
- **Deleted families:** delete stays finance-only when the family has payments (`src/api/routes/people/people-family.php:458-466`) and keeps `plg_FamID` so totals reconcile; reports show "Deleted family #N"; a `faud` row records it; refused while the family owns a pending, active, past_due or review recurring gift. The delete page first offers "Move Donations to Selected Family" (`src/SelectDelete.php:126-146`), which GIV-05 moves onto the audited attribution service (F8, family donation move).

---

## 6. Security, PCI, privacy, consent, receipts

| # | Threat | Control | Child PR (§7) |
|---|---|---|---|
| T1 | Card data reaches ChurchCRM | Hosted redirect; brand and last 4 only; paper card line removed | GIV-20, 22, 24, 51 |
| T2 | Forged webhook | Raw-body signature; re-fetch; mode and install checks after re-fetch | GIV-12, 16 |
| T3 | Duplicate or out-of-order events | UQ event id and external ref; row lock; state machine | GIV-16 |
| T4 | Tampered amount or fund | Sessions built from `gint`; mismatch → review | GIV-16 |
| T5 | IDOR, masquerade | UUIDs; actor-scoped queries; no id params; 409 on writes; impersonator audited | GIV-31, 32 |
| T6 | Card testing | Stripe's hosted checks; per-session 3 failures/15 min; per-email 5 intents/h; per-IP 60/10 min via `ClientIp`; honeypot; $5 minimum; generic declines; circuit breaker emails admins; Turnstile in GIV-60 | GIV-11, 33 |
| T7 | CSRF bypass via `X-API-Key` | `StrictCSRFMiddleware` | GIV-11 |
| T8 | Secret leak | Encrypted, write-only; never in `getPluginsClientConfig()` (`PluginManager.php:880`), hooks, audit, logs; writes checked against the manifest (`src/plugins/routes/api/management.php:268-274`); `rk_` keys; key outside DB | GIV-13, 15 |
| T9 | Gateway disabled mid-subscription | `canDeactivate()`, receive-only, 503 + alert, catch-up | GIV-12 |
| T10 | Test money in books | Mode on rows; go-live purge; clone detection | GIV-15 |
| T11 | Insider fraud | I4; original-method refunds; TOTP step-up; audit; alert | GIV-03, 41 |
| T12 | Deposit delete wipes gifts | Delete guards | GIV-01, 16 |
| T13 | Wrong year, rounding | UTC → `sTimeZone`; server FY; integer cents | GIV-04, 16 |
| T14 | No HTTPS | Readiness from `$URL` | GIV-15 |
| T15 | Open or rewritten redirect | Server-built URLs; host allow-list (D8); login location validated by `AuthenticationManager::validateRedirectPath()` | GIV-31, 33 |
| T16 | Leaky errors | Generic JSON; no bodies logged; `giving` channel | GIV-12 |
| T17 | Gateway impersonation | `register(owner, gateway)`; core decided by path; no community id may equal a core id | GIV-10 |
| T18 | Webhook during upgrade | No auto-upgrade in webhook boot; 503 | GIV-12 |

**PCI.** Redirect pages host no payment fields: SAQ-A, and FAQ 1588's script criterion applies only to pages embedding a provider's form. Controls on overridable pages (D8): server-built redirect, host allow-list, readiness warning. GIV-60 adds, for a small non-overridable payment partial only: enforced page CSP, SRI, no plugin/theme JS in the partial, CSP reports as admin alerts, script inventory; Stripe.js from `js.stripe.com` via `@stripe/stripe-js/pure`, never bundled.

**Idempotency roots:** Stripe `gint-{uuid}-create`, `cancel:{uuid}`, `refund:{id}:{n}`; PayPal `PayPal-Request-Id` `capture:{uuid}` (capture on return and on `CHECKOUT.ORDER.APPROVED`); BTCPay `orderId`.

**Logging.** New `LoggerUtils::getGivingLogger()` like the auth logger (`LoggerUtils.php:205`, no PostHog `:237`; the app logger feeds PostHog, `:182`); no names, emails or amounts in messages or context. Giving tables hold IPs only as HMACs, keyed outside the DB (§3.4); app and Slim logs keep plaintext `REMOTE_ADDR` (`LoggerUtils.php:175,231`; `src/ChurchCRM/Slim/SlimUtils.php:161,275`) for 3 days.

**Privacy**
- No auto-matching of anonymous gifts; staff re-attribute only on request, audited.
- A guest's email sits on `gint_DonorEmail`, `gcon_Email` (if opted in), `gntf_ToEmail`, and the email log once #9876 lands. Anonymous receipts log with a "do not resolve" context, because `EmailLogService::logSend()` otherwise matches the address to a person or family (`src/ChurchCRM/Service/EmailLogService.php:42-75`, `:65-67` [portal branch, #9876]); upstream `BaseEmail` does not log (`src/ChurchCRM/Emails/BaseEmail.php:52-59`).
- **Erase guest donor PII** (Finance, audited) redacts email and name on `gint`, `gcon`, `gntf`, email-log rows [#9876] and stored `gwhe` payloads; money stays.
- **Person delete** (`Person::preDelete`, `src/ChurchCRM/model/ChurchCRM/Person.php:653`) clears the person's ids: `plg_PerID`, `gtxn_per_ID`, `gint_per_ID`, `grec_per_ID` and `gcus_per_ID` (the family keeps its gifts; audited). It is refused while the person owns a pending, active, past_due or review recurring gift, and while any of their rows has `plg_FamID` NULL: clearing those would turn a named gift into an anonymous one (I6), so Finance re-attributes them first. Family delete: §5.4.
- **Retention:** pledge rows, `gtxn`, `gnc`, `faud` permanent · `gwhe_Payload` 90 days · IP hashes 30 days · abandoned `gint` 180 days · sent `gntf` 1 year · consent while subscribed + 2 years.

**Consent (D4).** Single opt-in, checked by default, shown once an email is entered, separate from the receipt, sent only after the gift succeeds. With Mailchimp: new `MailChimpService::upsertSubscriber()` → `PUT /lists/{id}/members/{md5(lowercase email)}`, `status_if_new=subscribed`, tag `giving`, to the plugin's unused `defaultListId`; Mailchimp's opt-in settings govern only its own forms, and its Acceptable Use Policy requires consent evidence, which `gcon` keeps. Without Mailchimp: same rule, CSV export. No path claims double opt-in; the help text says a pre-checked box is not valid consent under GDPR or CASL (set unchecked or off there); GIV-71 reviews. `fam_SendNewsLetter` is untouched.

**Receipts (US).** Organisation, amount, date, number, goods-and-services wording. Each receipt is the contemporaneous acknowledgment for a single gift of $250+ (gifts are not aggregated). Over $75 with goods: deductible limited to the excess over the good-faith FMV (`di_estprice`). Crypto is property: asset and quantity, no value; > $5,000 flagged for Form 8283 Section B (qualified appraisal, church signs the donee part); disposal within 3 years tracked for Form 8282.

**Sources (checked 2026-09-24):** https://raw.githubusercontent.com/php/frankenphp/main/frankenphp.stub.php · https://www.php.net/manual/en/ref.fpm.php · https://github.com/paypal/PayPal-PHP-Server-SDK · https://docs.stripe.com/apple-pay?platform=web · https://docs.stripe.com/payments/payment-methods/pmd-registration · https://docs.stripe.com/reports/balance-transaction-types · https://docs.stripe.com/standalone-fees · https://docs.stripe.com/payments/checkout/billing-cycle?payment-ui=stripe-hosted · https://developer.paypal.com/api/rest/webhooks/event-names/ · https://developer.paypal.com/api/subscriptions/v1/subscriptions-transactions · https://docs.btcpayserver.org/Subscriptions/ · https://raw.githubusercontent.com/stripe/stripe-php/master/composer.json · https://blog.pcisecuritystandards.org/faq-clarifies-new-saq-a-eligibility-criteria-for-e-commerce-merchants · https://mailchimp.com/developer/marketing/api/list-members/add-or-update-list-member/ · https://www.irs.gov/pub/irs-pdf/p1771.pdf · https://www.irs.gov/instructions/i8283

---

## 7. Proposed breakdown

Nothing here is filed yet (D12). One concern and one PR per child; each user-visible child opens a user-manual docs issue. Each issue will carry its own scope and acceptance criteria. CI uses `FakeGateway` (`CHURCHCRM_GIVING_SANDBOX=1`, §2.4), never a real gateway.

**Sequencing:** phases A–D, F and GIV-50 can go upstream before #8977. Phase E and GIV-51 wait for #8977 (and #9843 for the masquerade guard). Online giving stays hidden until GIV-20 to GIV-24 are merged (D1); office-side work (phase A, GIV-50, the #9313 reports in GIV-43) is visible as soon as it merges.

| Id | Title | Design | Depends on |
|---|---|---|---|
| **A** | **Finance foundations (no gateway)** | | |
| GIV-01 | Remove dead e-payment code (`setDeposit`, Load Authorized, Run Transactions); refuse deletes in closed deposits; fire `DEPOSIT_CLOSED` on close | F13, §2.4 | – |
| GIV-02 | Anonymous = NULL family; "Anonymous" label; LEFT JOINs and refund netting in summaries | §3.1, §5.4 | – |
| GIV-03 | Append-only finance audit log | §3.2 `faud` | – |
| GIV-04 | `PledgeWriter` (session-less, server FY, PDO GroupKeys, `familyOf()`); `Person::ONLINE_GIVING`; `GIFT_RECORDED` | §2.1, F0 | 03 |
| GIV-05 | Per-person attribution (#9313): `plg_PerID`; `PersonID` rule on all write routes; attribution service and PATCH; family donation move; person delete rule; donor count | §3.1, F8, §5.4, §6 | 02, 04 |
| GIV-06 | "Given by" in the editor, person and family views; D11 move | F8 | 05 |
| GIV-07 | `GivingStatementService`, `GivingScope`, tax-statements view, head-list fixes | §5.3 | 05 |
| **B** | **Platform** | | |
| GIV-09 | Plugin-local `vendor/` (D10) | §2.6 | – |
| GIV-10 | Contract; registry (lazy owner check, core by path, no community id equal to a core id); `payments.process`; release gate; `FakeGateway` fixture | §2.2–2.4 | – |
| GIV-11 | Rate limiter, `ClientIp`, `StrictCSRFMiddleware`, circuit breaker | §6 T6–T7 | – |
| GIV-12 | Webhook ingress in the plugin folder; webhook boot mode (no session, no auto-upgrade, no PostHog handler); DB gate; lifecycle guard; `getGivingLogger()` | §2.5 | 10, 11, 14 |
| GIV-13 | Encrypted `secret` settings with the key outside the DB; giving HMAC key derivation | A15, §3.4 | – |
| GIV-14 | Giving schema: §3.1 columns (incl. `plg_fr_ID`, `plg_pn_ID`), §3.2 tables, utf8mb4 | §3 | 05 |
| GIV-15 | `/admin/online-giving`, settings, readiness, test purge, `FeeCalculator`, `AllocationSplitter`, `RecurrenceScheduler`, install identity | §3.4, §5.3, F3, F9, F13 | 10, 13, 14 |
| **C** | **Engine** | | |
| GIV-16 | Intents; `GivingRecorder`; `WebhookProcessor`; batch deposits; gateway-group and gateway-deposit guards; reconcile; nightly invariants | F0, F13, F14, §2.5 | 04, 12, 14, 15 |
| GIV-17 | Notification outbox (flush after commit, drain in reconcile) and receipts | §3.2 `gntf`, §6 | 16 |
| **D** | **Gateways (all three before release, D1)** | | |
| GIV-20 | Stripe, one-time | §2.3, F10 | 09, 13, 16, 17 |
| GIV-21 | Stripe recurring; `RecurringGiftService` | F3–F6 | 20 |
| GIV-22 | PayPal, one-time (Orders v2) | §2.3, F6 | 13, 16, 17 |
| GIV-23 | PayPal recurring (Subscriptions v1) | F3–F6, D7 | 21, 22 |
| GIV-24 | BTCPay Server; non-cash recording | F11 | 13, 16, 17; 07 for the statement section |
| **E** | **Donor surfaces (after #8977)** | | |
| GIV-30 | Portal Giving section, History, downloads, `bPortalShowGiving` | §5.1 | #8977, 07 |
| GIV-31 | Give tab, one-time | F1, §5.1 | 30, 11, 16, 17, one of 20/22/24, #9843 |
| GIV-32 | Recurring in the portal: notices, More Info, Cancel | F3, F5 | 31, 21 or 23, #9843 |
| GIV-33 | Public anonymous page | F2, §5.2 | 31 |
| GIV-34 | Newsletter opt-in: checkbox, `gcon`, F0 consent step, Mailchimp, CSV | F2, §6 Consent | 33 |
| **F** | **Finance operations** | | |
| GIV-40 | Finance dashboard and review queue | §5.3 | 03, 16, 17 |
| GIV-41 | Refunds, disputes, staff cancel and Resume, guest PII erase (TOTP) | F7, §6 | 40; 20 or 22; 21 or 23 for staff cancel |
| GIV-42 | Stripe payout deposits; deposit slip and exports | F13, §5.4 | 16, 20 |
| GIV-43 | Giving reports, including #9313's | §5.4 | 07, 16, 24 |
| **G** | **Fundraisers** | | |
| GIV-50 | Buyer balances by paddle; editor paddle picker; Collected | F12 | 14 |
| GIV-51 | Online balance payments; pay links | F12 | 17, 31, 33, 50 |
| **H** | **Later and cross-cutting** | | |
| GIV-60 | Embedded checkout, on-page wallets, CSP builder, Turnstile | D5, D8, F10 | 20, 22, 24, 31 |
| GIV-70 | Docs, E2E, i18n (rolling) | – | each item after the issue it covers |
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
| Q5 | Statement mode default? | Household. |
| Q6 | Refund alert threshold? | $500. |
| Q7 | Amount limits? | Public $5–$10,000; members $1–$25,000. |
| Q8 | Fee defaults? | Standard published rates, with nonprofit-rate help. |
| Q9 | PayPal "Last day"? | Hidden until a sandbox test proves month-end billing. |
| Q10 | Receipt for every recurring charge? | Yes. |
| Q11 | Nav position? | Between My Family and Profile, plus a Give card on the portal home. |
| Q12 | No HoH or several heads? | All of `getHeadPeople()` see the household; office list of headless families. |
| Q13 | Fundraiser pay links? | 30 days, 5 uses; Manage Fundraisers or Finance may send. |
| Q14 | Stripe ACH? | Included, off. |
| Q15 | Batch period? | Weekly; payout mode stays opt-in. |
| Q16 | Masquerade reads of History? | Only for finance-enabled impersonators. |
| Q17 | TOTP step-up for staff cancels too? | Yes. |
| Q18 | Deceased or deactivated recurring owner? | Alert only; staff decide. |
| Q19 | Test-mode gifts? | "[TEST]" deposits, purged at go-live. |

### 8.2 For the maintainer
The six questions are listed at the top of this document, under "Questions for the maintainer".

---

## 9. Features beyond the core requirements

| Feature | Proposal |
|---|---|
| Receipts with IRS wording and numbers; printable on-screen receipt; portal year statement | Keep (GIV-17/30) |
| Split gifts; refunds, disputes, chargebacks as negative rows; review queue | Keep (GIV-31/40/41) |
| Automatic deposits with gross/fees/net; payout-exact deposits (opt-in); catch-up sync and webhook health | Keep (GIV-16/42) |
| Finance audit log; test mode with purge and clone detection; encrypted secrets; core rate limiter | Keep (GIV-03/15/13/11) |
| Plugin-disable guard and receive-only; delete guards; past-due badge with Update payment method | Keep (GIV-12/21/32) |
| HoH transparency note; consent evidence; privacy and refund links; deep links and QR | Keep (GIV-31/34/71/33) |
| Paper card-number line removed from fundraiser statements | Keep (GIV-51) |
| WCAG 2.2 AA; gateway locale | Keep, as acceptance criteria |
| Recurring edit/pause; biweekly; dunning emails (gateways send their own); website iframe | Defer |
| PayPal Apple Pay; BTCPay recurring; automated BTCPay refunds; more gateways (#9575) | Defer; the contract fits |
| Import from other platforms (#9600) via `PledgeWriter`, deduplicated on external ref | Defer, aligned |
| Campaigns, pledge progress, tribute, text-to-give, kiosk; stock and in-kind gifts (reuse `gnc`) | Defer |
| CRA/Gift Aid; multi-currency; bulk statement email; two-person refund approval; QuickBooks export; paid events (#8181) | Defer |
| Dropping `plg_aut_*`, `result_res` | Defer (release after GIV-01) |
| Staff virtual terminal | Reject (PCI scope) |

---

## Appendix A. Requirements this design covers

Each requirement and each proposed product choice (D1–D12), with the sections that meet it.

| # | Requirement | Where | Notes |
|---|---|---|---|
| R1 | Payment gateway plugin design | §2.1–2.6, A20, GIV-10 | |
| R2 | Anonymous giving, admin entry | F8, GIV-02/06 | I6 |
| R3 | Anonymous giving, unauthenticated portal page | F2, §5.2, GIV-33 | Needs #8977 |
| R4 | Giving by persons, admin entry | F8, GIV-05/06 | Optional; `bFinanceShowGivenBy` |
| R5 | Giving by families, admin entry | F8 | Whole household or member |
| R6 | Giving by persons, portal | F1 | PerID = actor |
| R7 | Giving by families, portal | F1, §5.1, Q1 | Give as household |
| R8 | Portal Giving page, tabs Give and Giving History | §5.1 | History-only when giving is off |
| R9 | Give: electronic gift via enabled gateways | F1, §2.4 | `forGiving()` |
| R10 | Give: choose purpose | F1, §5.1, `fun_OnlineGiving` | Up to 5 funds |
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
| R40 | Non-HoH sees own gifts only | D3, A10 | Spouse included |
| R41 | HoH sees whole family | D3, A10, D11 | Incl. family-level |
| R42 | Admin reports updated similarly | §5.3, §5.4, GIV-07/43, A32 | #9313 reports |
| R43 | Fundraisers use gateways | F12, GIV-50/51 | Token on `pn_ID`; balance by `plg_pn_ID`; works with the public page off |
| R44 | Portal Apple Pay where allowed | D5, F10, GIV-60 | v1 on Stripe's hosted page |
| R45 | Other features and integration | §8, §9 | |
| R46 | Three gateways in the first release | D1, §2.4, §7 D | Release gate |
| R47 | Core contract, core plugins | §2.1, §2.6, D10 | SDK in plugin vendor |
| R48 | Public donor stays anonymous | F2.5, §6 | Email log not resolved |
| R49 | Optional receipt email on the public page | F2.2, F2.4, GIV-17 | Signed return token |
| R50 | Newsletter box checked by default | F2.2, §6, GIV-34 | Single opt-in; EU/CA setting |
| R51 | "If you are a member, click here" link | F2.2 | Shown only when the portal Give tab is available; plus "Contact the church office" |
| R52 | Login returns to the Give page | F2.6 | `AuthMiddleware.php:197-215` |
| R53 | 7.8.0, `7.8.0-*.sql`, not in `upgrade.json` | §3 | |
| D1 | Three gateways; release gate | §2.4, §7 | |
| D2 | Upstream; core contract; core plugins | §2.1, §2.6 | |
| D3 | HoH / non-HoH visibility | A10, §5.1, §5.3 | |
| D4 | Public page, receipt, newsletter, member link | F2, §6 | |
| D5 | Hosted redirect; wallets on Stripe | A1, F10 | |
| D6 | First charge on chosen day | F3, A27 | |
| D7 | PayPal Subscriptions v1 | §2.3, F3, GIV-23 | |
| D8 | Overridable pages; server-built redirect | A26, F1.4, §6 | |
| D9 | Single 'Online' type | A7, §3.1, F13 | |
| D10 | Plugin-local vendor | §2.6, GIV-09 | |
| D11 | Moving families | A31, F4, F8, GIV-06 | |
| D12 | Proposal first; issues only after the direction is accepted | Status, §7 | |
