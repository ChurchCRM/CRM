# ChurchCRM — Forensic Technical README

> **Version:** 7.4.0 · **License:** MIT · **Homepage:** [churchcrm.io](https://churchcrm.io)  
> **Documentation:** [docs.churchcrm.io](https://docs.churchcrm.io) · **Community:** [Discord](https://discord.gg/tuWyFzj3Nj)

This README is a **principal-architect forensic record** of the ChurchCRM codebase: genesis, architecture, every major feature surface, known defects, and the narrative from conception to the current tree. It is written for senior developers onboarding onto the project instantly.

**Table of contents**

1. [Genesis & Evolution](#1-genesis--evolution)
2. [Core Architecture](#2-core-architecture)
3. [Functional Deep-Dive](#3-functional-deep-dive)
4. [Known Issues & Technical Debt](#4-known-issues--technical-debt)
5. [Conception to Current State](#5-conception-to-current-state)
6. [Quick Start (Developer)](#6-quick-start-developer)
7. [Community & Contribution](#7-community--contribution)

---

## 1. Genesis & Evolution

### 1.1 Origin (2004 — ChurchInfo / InfoCentral)

| Fact | Detail |
|------|--------|
| **First commit** | `2004-08-20` — `7f49135d6` — *"initial checkin"* |
| **Total commits (this tree)** | **14,483** (as of forensic scan) |
| **Original name** | **ChurchInfo** (successor to **InfoCentral**) |
| **Original stack** | PHP + MySQL LAMP, page-per-script architecture, `register_globals` mitigation (`2c2cfc298` — *"This file turns off register_globals for php scripts"*) |
| **Early domain** | Congregation records, pledges, deposit slips, MICR check reading, donation funds, directory reports |

The earliest preserved commits reference **InfoCentral → ChurchInfo** rebranding (`1c23cf83d` — *"Updates to reflect changes from InfoCentral to ChurchInfo"*), donation fund tables (`d334f9c42`), deposit slip editors, and report configuration classes. The project was imported via **CVS→SVN→Git** (`cvs2svn` compensation commits appear in the first 20 revisions), which explains sparse early commit messages ("no message").

**Why PHP + MySQL (original choice):** Shared hosting availability for churches (2004 era); zero license cost; page-script model matched volunteer-maintainer skill sets. This constraint still shapes deployment today — the app expects Apache `.htaccess` or explicit nginx/Caddy per-subdirectory routing.

### 1.2 ChurchCRM Era (~2015 — present)

| Milestone | Version / Date | Architectural significance |
|-----------|----------------|---------------------------|
| **ChurchCRM branding** | ~2015 (10th anniversary noted in 7.0.0, Feb 2026) | Community fork/evolution from ChurchInfo; GitHub-centric workflow |
| **Slim framework adoption** | 3.x → **Slim 4** (current) | Multiple independent Slim apps under `src/*/index.php` instead of one monolithic router |
| **Propel ORM → Perpl ORM** | Perpl `^2.6.0` (`perplorm/perpl`) | Actively maintained Propel2 fork; PHP 8.4 compatibility; 30–50% faster query building per project docs |
| **PHP 8.4 minimum** | **7.0.0** (Feb 2026) | Platform pinned in `composer.json`; PHP \<8.4 unsupported |
| **Plugin system** | **7.0.0** | WordPress-style hooks; core integrations (MailChimp, Vonage, OpenLP, etc.) extracted from core |
| **Leaflet maps** | **7.0.0** | Replaced Google Maps / Bing Maps dependency |
| **AdminLTE + Bootstrap 4** | Pre-7.1 | Legacy UI shell |
| **Tabler + Bootstrap 5** | **7.1.0** (Apr 2026) | Complete UI modernization; AdminLTE removed |
| **React removal** | **7.1.2** | Frontend returned to jQuery + Webpack vanilla JS/TS modules |
| **Community plugin registry** | **7.3.0** | Remote approved-plugin registry via `ApprovedPluginRegistry` |
| **MvcAppFactory** | **7.1.2** | Standardized Slim MVC module bootstrap |
| **Functions.php deletion** | **7.1.0** | 17 global helpers migrated to typed `Utils/` classes |
| **Current release line** | **7.4.0** (`package.json`, `composer.json`) | Node ≥24, Cypress 15, Biome lint |

### 1.3 Major Pivots and Rejected Alternatives

| Decision | Chosen | Rejected / Deprecated | Rationale (documented) |
|----------|--------|----------------------|------------------------|
| **Database** | MariaDB/MySQL | SQLite, Postgres, Turso/libSQL | 20+ years of schema, views, Perpl schema in `orm/schema.xml`; no alternate driver |
| **ORM** | Perpl Query classes only | Raw SQL, `RunQuery()` | Security (SQLi history in 6.4.0); consistency |
| **UI framework** | Tabler + Bootstrap 5 | AdminLTE, Bootstrap 4 | Epic [#8301](https://github.com/ChurchCRM/CRM/issues/8301); responsive/mobile goals |
| **Frontend SPA** | Webpack bundles (jQuery + TS modules) | React (removed 7.1.2) | Maintenance cost; server-rendered PHP templates preferred |
| **Maps** | Leaflet (open source) | Google Maps API, Bing Maps | Cost, privacy, Bing retirement |
| **Deployment target** | Apache / nginx / FrankenPHP Docker | Vercel, serverless PHP | Multi-entry PHP apps, sessions, uploads, long requests |
| **Build** | Webpack + Grunt (dual pipeline) | Single bundler | Grunt copies vendor assets to `src/skin/external/`; Webpack builds `src/skin/v2/` |
| **i18n** | gettext (PHP) + i18next (JS) + POEditor | Inline strings only | 46+ locales; `locale/messages.po` workflow |
| **Testing** | Cypress E2E (161 specs) | PHPUnit unit suite (minimal) | Integration-first; API + UI in Docker CI |

### 1.4 Version Support Matrix

From `SECURITY.md`:

| Version | Security support | PHP |
|---------|-----------------|-----|
| **7.1+** | ✅ Latest only | ≥8.4 |
| 7.0.x | ❌ | ≥8.3 |
| 6.x | ❌ | ≥8.2 |
| ≤5.x | ❌ | 7.x–8.1 |

---

## 2. Core Architecture

### 2.1 Repository Topology

```
CRM/
├── src/                    # Web document root (PHP application)
│   ├── index.php           # Legacy front controller (non-Slim)
│   ├── api/                # REST API (Slim 4)
│   ├── admin/              # Admin MVC module
│   ├── finance/            # Finance MVC module
│   ├── people/             # People MVC module
│   ├── groups/             # Groups MVC module
│   ├── event/              # Events MVC module
│   ├── v2/                 # App shell (dashboard, email, cart, map)
│   ├── session/            # Login, 2FA, logout
│   ├── setup/              # First-run wizard (pre-Config.php)
│   ├── external/           # Public registration/verify/calendar
│   ├── kiosk/              # Check-in kiosk devices
│   ├── plugins/            # Plugin runtime + core plugins
│   ├── ChurchCRM/          # Core library (services, models, auth, slim, utils)
│   ├── Include/            # Config.php, LoadConfigs.php (bootstrap)
│   ├── mysql/              # install/ + upgrade/ SQL migrations
│   ├── Reports/            # 24 legacy report scripts
│   ├── templates/          # Twig/PHP view templates
│   ├── skin/v2/            # Webpack output (gitignored)
│   └── skin/external/      # Grunt-copied vendor assets (gitignored)
├── orm/                    # Perpl schema.xml + propel config
├── webpack/                # JS/TS/SASS entry sources
├── cypress/                # E2E tests (161 specs)
├── docker/                 # Compose profiles: dev, test, ci, nginx, frankenphp
├── locale/                 # gettext PO + i18n build scripts
├── scripts/                # Build, package, validate, plugin scan
├── changelog/              # Per-version release notes
└── package.json            # Node 24, Webpack, Cypress, Grunt
```

### 2.2 Multi-Application Slim 4 Pattern

ChurchCRM is **not** one Slim app. Each URL prefix boots its own `index.php`:

| URL prefix | Entry point | Auth middleware | Route files |
|------------|-------------|-----------------|-------------|
| `/` | `src/index.php` | `AuthenticationManager` (legacy) | Filesystem → `CamelCase.php` |
| `/api/` | `src/api/index.php` | `AuthMiddleware` | 33 route files under `api/routes/` |
| `/admin/` | `src/admin/index.php` | `AdminRoleAuthMiddleware` | 14 MVC + admin API routes |
| `/finance/` | `src/finance/index.php` | `FinanceRoleAuthMiddleware` | dashboard, reports, pledges |
| `/people/` | `src/people/index.php` | Default MVC auth | dashboard, list, family, person, view |
| `/groups/` | `src/groups/index.php` | `ManageGroupRoleAuthMiddleware` | dashboard, reports, sundayschool, view |
| `/event/` | `src/event/index.php` | `ViewEventsRoleAuthMiddleware` | 9 route files |
| `/v2/` | `src/v2/index.php` | Default MVC auth | dashboard, email, text, cart, map |
| `/session/` | `src/session/index.php` | Public login routes | begin, end, 2FA, password-reset |
| `/setup/` | `src/setup/index.php` | Pre-install | setup wizard |
| `/external/` | `src/external/index.php` | None (public) | register, verify, calendar |
| `/kiosk/` | `src/kiosk/index.php` | Kiosk device cookie | device, admin, API |
| `/plugins/` | `src/plugins/index.php` | Admin for management | dynamic per-plugin routes |

**Middleware order (LIFO on API):** `addBodyParsing` → `addRouting` → `CorsMiddleware` → `AuthMiddleware` → `VersionMiddleware`

**Critical deployment note:** nginx/Caddy must map **each prefix** to its own `index.php`. Routing all traffic to root `index.php` causes infinite redirect loops (`/session/begin` → root → redirect again). Documented in `docker/README.md`.

### 2.3 Bootstrap & Configuration Chain

```
HTTP Request
  → src/{module}/index.php OR src/index.php OR src/{Page}.php
  → src/Include/LoadConfigs.php
  → src/Include/Config.php (gitignored; example: Config.php.example)
  → ChurchCRM\Bootstrapper::init()
       ├── SystemURLs::init()
       ├── MySQLi connection test
       ├── Perpl ORM (Propel runtime)
       ├── Session init
       └── SystemConfig::init() from config_cfg table
```

`Bootstrapper` auto-installs schema from `mysql/install/Install.sql` if DB is empty; upgrades run via `UpgradeService` + `mysql/upgrade.json` (47 upgrade scripts).

### 2.4 Data Layer

| Component | Path | Detail |
|-----------|------|--------|
| **Schema** | `orm/schema.xml` | **46 unique tables** (e.g. `person_per`, `family_fam`, `events_event`, `pledge_plg`, `deposit_dep`) |
| **Generated models** | `src/ChurchCRM/model/ChurchCRM/` | Base + Map classes (gitignored generated dirs) |
| **Query access** | `*Query` classes | **Mandatory** — no raw SQL in application code |
| **Migrations** | `src/mysql/upgrade/` + `upgrade.json` | Version-keyed; `rebuild_views.sql` always runs post-upgrade |
| **Demo seed** | `cypress/data/seed.sql` | Used by Docker MariaDB init and local dev |

**Storage convention (timezone):** Event DATETIME columns store **wall-clock in church timezone** (`sTimeZone` config), not UTC. Changing this requires data migration (documented in `.agents/skills/churchcrm/timezone-handling.md`).

### 2.5 Service Layer (`src/ChurchCRM/Service/`)

Business logic lives in **22 service classes** (never in route handlers):

| Service | Responsibility |
|---------|---------------|
| `AdminService` | Admin dashboard warnings, URL validation |
| `AppIntegrityService` | File signatures, prerequisites, orphaned file cleanup |
| `AuthService` | Auth/authz helpers wrapping `AuthenticationManager` |
| `DashboardService` | Root dashboard statistics |
| `DemoDataService` | Demo JSON import from `admin/demo/` |
| `DepositService` | Deposit slips, PDF/CSV export |
| `DonationFundService` | Fund CRUD and ordering |
| `EventService` | Recurring/bulk event creation |
| `FamilyPledgeSummaryService` | Pledge summaries by fund/year |
| `FamilyService` | Geocoding, missing coordinates |
| `FinancialService` | Payments, MICR, check validation |
| `GroupService` | Membership, roles, group properties |
| `LocaleService` | Locale metadata, OS locale detection |
| `NotificationService` | In-app notifications (upgrade alerts) |
| `PersonService` | Search, directory, volunteer opportunities |
| `PropertyService` | Person/family/group properties |
| `SundaySchoolService` | Class stats, rosters |
| `SystemService` | SystemConfig, DB version, cron timers |
| `TimelineService` | Person/family notes + events timeline |
| `UpgradeAPIService` | GitHub release check/download |
| `UpgradeService` | DB upgrade script execution |
| `UserService` | User listing, lockout, 2FA stats, settings |

### 2.6 Frontend Build Pipeline

| Stage | Tool | Output |
|-------|------|--------|
| **Legacy vendor copy** | Grunt (`grunt copy`) | `src/skin/external/` (FullCalendar, Moment, DataTables, Leaflet, etc.) |
| **Modern bundles** | Webpack 5 (34 entries) | `src/skin/v2/*.min.js`, `*.min.css` |
| **Format** | Biome | `webpack/` lint; `format:web` on build |
| **Signatures** | `scripts/generate-signatures-node.js` | `src/admin/data/signatures.json` (957 files) |
| **Locale** | `locale/scripts/locale-build.js` | JS i18n JSON in `src/locale/i18n/` |

**Key runtime deps:** Tabler, Bootstrap 5.3, jQuery 3.7, FullCalendar 6, DataTables 2, i18next, Tom Select, Uppy, ApexCharts, Quill 2.0.2, Leaflet.

### 2.7 Plugin Architecture (`src/ChurchCRM/Plugin/`)

- **Core plugins (8):** `custom-links`, `external-backup`, `google-analytics`, `gravatar`, `holidays`, `mailchimp`, `openlp`, `vonage`
- **Community plugins:** Installed at runtime to `src/plugins/community/` (gitignored)
- **Hooks:** `HookManager` + `Hooks` constants (e.g. `MENU_BUILDING`)
- **Registry:** Remote URL via `ApprovedPluginRegistry` (no local JSON in repo)

### 2.8 Authentication & Authorization

| Layer | Implementation |
|-------|---------------|
| **Session** | PHP sessions; cookie prefix `CRM-{hash}`; `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS |
| **Login** | `/session/begin` — `AuthenticationManager` |
| **2FA** | TOTP via `pragmarx/google2fa`; recovery codes on `User` model |
| **API auth** | Session cookie or API token patterns in `AuthMiddleware` |
| **Role flags** | `User::isAdmin()`, `isFinanceEnabled()`, `isEditRecordsEnabled()`, `canViewEvents()`, etc. |
| **CSRF** | Slim middleware on mutating routes (hardened 7.2.2, GHSA-3xq9-c86x-cwpp) |

### 2.9 Dependency Trees (Production)

**PHP (`src/composer.json`):** Slim 4.15, Perpl 2.6, Twig 3.26, PHPMailer 7, Monolog 3, endroid/qr-code, defuse/php-encryption, league/csv, vonage/client, knplabs/github-api, azuyalabs/yasumi (holidays), ifsnop/mysqldump-php.

**Node (`package.json`):** webpack 5, typescript 6, cypress 15, @biomejs/biome, grunt, sass — plus 30+ runtime UI libraries.

### 2.10 Test Infrastructure

| Category | Count | Config |
|----------|-------|--------|
| **API E2E** | 54 | `cypress/e2e/api/` |
| **UI E2E** | 102 | `cypress/e2e/ui/` |
| **New-system** | 5 | `cypress/configs/new-system.config.ts` |
| **Total** | **161** | `cypress/configs/docker.config.ts` |

Runners: `npm run test`, `test:api`, `test:ui`, `test:new-system`. CI uses Docker Compose profiles (`docker:ci:*` scripts).

---

## 3. Functional Deep-Dive

Features are organized by navigation module (`src/ChurchCRM/Config/Menu/Menu.php`). Status reflects **7.4.0 tree** with Cypress coverage where listed.

**Legend:** ✅ Working (Cypress covered) · ⚠️ Working (partial/legacy) · ❌ Known broken · 🔧 Technical debt

---

### 3.1 Dashboard (`/v2/dashboard`)

| Aspect | Detail |
|--------|--------|
| **Implementation** | `v2/routes/root.php` → `DashboardService` stats; `webpack/root-dashboard.js` |
| **Logic** | Counts families, people, groups, classifications, recent members; role-gated widgets |
| **Status** | ✅ — `cypress/e2e/ui/` dashboard specs; post-login redirect tested |
| **Tests** | UI regression in `css-regression.spec.js`, mobile in `mobile-ux.spec.js` |

---

### 3.2 Calendar (`/event/calendars`)

| Aspect | Detail |
|--------|--------|
| **Implementation** | `event/routes/calendar.php`; FullCalendar via `webpack/event-calendars.js`; system calendars in `events_event`, `calendars`, `calendar_events` tables |
| **Logic** | Birthday calendar (ID 0), anniversary (ID 1), church events; menu counters via `MenuCounter` |
| **Timezone** | Wall-clock in `sTimeZone`; see PR #8806 refactor |
| **Status** | ✅ — `private.calendar.*` API specs (events, timezone, checkin, audit, counters); `external.calendar.spec.js`, `mobile.calendar.spec.js` |
| **Edge cases** | FullCalendar marker quirks across TZ documented in timezone skill |

---

### 3.3 People Module

| Feature | Route / File | Implementation | Status |
|---------|--------------|----------------|--------|
| **People Dashboard** | `/people/dashboard` | MVC + `PersonService` | ✅ `admin.people.spec.js` |
| **Add Person** | `PersonEditor.php` | Legacy form + Perpl `Person` | ✅ `standard.person.new.spec.js` |
| **Person Listing** | `/people/list` | MVC + DataTables `people-list` bundle | ✅ `standard.person.list.spec.js`, `standard.person.search.spec.js` |
| **Person View (MVC)** | `/people/view/{id}` | Slim MVC + Tabler (#8479) | ✅ `standard.person.profile.spec.js` |
| **Photo Directory** | `/people/photos` | Gallery + Uppy | ✅ `standard.photo-gallery.spec.js` |
| **Add Family** | `FamilyEditor.php` | Legacy 52KB form | ⚠️ Legacy — ✅ `standard.family.spec.js` |
| **Family Listing** | `/people/family` | MVC `people-family-list` | ✅ `standard.family.list.spec.js` |
| **Family View** | `/people/family/view/{id}` | MVC `people-family-view` | ✅ family specs |
| **Family Map** | `/v2/map` | Leaflet + geocoded families | ✅ `private.map.families.spec.js` |
| **Family Roles** | `/admin/system/options?mode=famroles` | `ListOption` type 2 | ✅ admin options API |
| **Family Properties** | `PropertyList.php?Type=f` | `PropertyService` | ✅ `family.properties.spec.js` |
| **Family Custom Fields** | `FamilyCustomFieldsEditor.php` | `family_custom_master` | ⚠️ Legacy UI — admin specs |
| **Person Classifications** | `admin/system/options?mode=classes` | `ListOption` type 1 | ✅ |
| **Person Properties** | `PropertyList.php?Type=p` | XSS patched 6.7.2 (GHSA-8r36-fvxj-26qv) | ✅ |
| **Person Custom Fields** | `PersonCustomFieldsEditor.php` | Fixed fatal 7.1.0 (#8474) | ✅ `person-custom-fields.spec.js` |
| **Volunteer Opportunities** | `VolunteerOpportunityEditor.php` | `volunteeropportunity_vol` table | ✅ `standard.volunteer-opportunity.spec.js` |
| **Notes / Timeline** | API + person view | `TimelineService`; REST notes API (#8856) | ✅ `private-notes-timeline-ui.spec.js` |
| **Cart (people)** | `/api/cart` + session | Add families/persons to cart | ✅ `private.people.family.cart.spec.js` |
| **CSV Import** | `/admin/import` | Drag-drop, Propel execution (#8299) | ✅ `csv-import` admin specs |
| **CSV Export** | `/admin/export` | Admin-only after GHSA-4vj2-gm78-3q63 (#8927) | ✅ `private.admin.csv.export.spec.js` |
| **Self-registration** | `/external/register` | Public Slim routes | ✅ `guest.family.reg.spec.js` |
| **Family verification** | `/external/verify` | Email token flow | ✅ `family.verify.spec.js`, `family.verify-modal.spec.js` |
| **Photo upload** | API + Uppy | `photo-uploader` webpack entry | ✅ `private.people.photo.spec.js` |
| **Geocoding** | API `/api/geocoder` | `FamilyService` automation | ⚠️ Requires external geocoder config |
| **Filter by classification** | Person list API | Query filters | ✅ `standard.people.filterbyclassification.spec.js` |
| **People without email** | API | Report endpoint | ✅ `people.without-email.spec.js` |

---

### 3.4 Groups Module

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **Groups Dashboard** | `/groups/dashboard` | MVC + `GroupService` | ✅ `ui/groups/` specs |
| **Group View** | `/groups/view/{id}` | MVC + roles/members | ✅ |
| **Group Properties** | `PropertyList.php?Type=g` | `property_pro` | ✅ |
| **Group Types** | `admin/system/options?mode=grptypes` | `ListOption` type 3 | ✅ |
| **Kiosk Manager** | `/kiosk/admin` | `KioskDevice` model | ✅ kiosk specs |
| **Add to group** | API + editors | `GroupService` | ✅ Fixed 7.1.0 (#8345) |
| **Email export** | API | Group member export | ✅ `private.groups.email-export.spec.js` |
| **Empty cart to group** | API | Cart workflow | ✅ `private.cart.empty-to-group.spec.js` |

---

### 3.5 Sunday School

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **SS Dashboard** | `/groups/sundayschool/dashboard` | `SundaySchoolService` | ✅ `standard.sundayschool.spec.js` |
| **SS Class View** | `/groups/sundayschool/class/{id}` | Groups type=4 | ✅ `groups-sundayschool-class-view` bundle |
| **Enable flag** | `bEnabledSundaySchool` | `SystemConfig` | ✅ |

---

### 3.6 Communication

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **Email dashboard** | `/v2/email/dashboard` | Recipient selection, PHPMailer | ✅ email specs |
| **Text/SMS dashboard** | `/v2/text/dashboard` | Vonage plugin integration | ⚠️ Requires Vonage plugin configured |
| **MailChimp sync** | Plugin | `plugins/core/mailchimp` | ✅ Plugin tests partial |
| **Property exclusions** | SystemConfig | Unified communication (#8386) | ✅ |

---

### 3.7 Events Module

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **Events Dashboard** | `/event/dashboard` | Stats + Tabler cards (#8352) | ✅ `admin.events.dashboard.spec.js` |
| **Add/Edit Event** | `/event/editor` | `event-editor.js`, wall-clock TZ (#8806) | ✅ `standard.events.spec.js` |
| **Repeat events** | `/event/repeat-editor` | `EventService` recurrence | ✅ repeat editor bundle |
| **Check-in/out** | `/event/checkin` | `event-checkin.js`, attendance tables | ✅ `private.calendar.events-checkin.spec.js`, #8807 avatar fixes |
| **Event Types** | `/event/types` | `event_types` table | ✅ `event-types.spec.js` |
| **Event audit** | `/event/audit` | Attendance audit trail | ✅ `private.calendar.events-audit.spec.js` |
| **Cart to event** | API | Add cart members to event | ✅ `event-cart-to-event` bundle |
| **Kiosk events** | `/kiosk/` | `kiosk-jsom.ts` heartbeat | ✅ `kiosk.api.spec.js` |
| **Safe deletion** | API | Orphan cleanup 7.3.x | ✅ |

---

### 3.8 Finance Module

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **Finance Dashboard** | `/finance/` | MVC Tabler | ✅ `finance.dashboard.spec.js` |
| **View Deposits** | `FindDepositSlip.php` | Legacy list | ⚠️ Legacy — ✅ `finance.deposits.spec.js` |
| **Edit Deposit Slip** | `DepositSlipEditor.php` | `DepositService`, MICR | ✅ deposit API + UI specs |
| **Deposit Reports** | `/finance/reports` | MVC reports | ✅ `finance.reports-index.spec.js` |
| **Pledge Dashboard** | `/finance/pledge/dashboard` | `FamilyPledgeSummaryService` | ✅ `finance.pledge-operations.spec.js` |
| **Payments API** | `/api/finance/payments` | `FinancialService` | ✅ `finance-payments.spec.js` |
| **Deposits API** | `/api/finance/deposits` | `DepositService` | ✅ `finance-deposits.spec.js` |
| **Donation Funds API** | `/api/finance/donation-funds` | `DonationFundService` | ✅ `finance-donation-funds.spec.js` |
| **Fundraisers API** | `/api/finance/fundraisers` | Fundraiser tables | ✅ `finance-fundraisers.spec.js` |
| **Envelope Manager** | `ManageEnvelopes.php` | Legacy | ⚠️ Legacy UI |
| **Donation Funds Editor** | `DonationFundEditor.php` | Admin legacy form | ⚠️ Active field bug fixed 7.1.0 (#8319) |
| **Tax Report PDF** | `Reports/TaxReport.php` | FPDF generation | ✅ `tax-report-pdf.spec.js`; memory fix 6.7.1 |
| **Payment submission** | UI flow | Finance role gated | ✅ `finance.payment-submission.spec.js` |
| **Financial Reports** | `FinancialReports.php` + Reports/ | Legacy report engine | ⚠️ — ✅ `financial-reports-issue-7854.spec.js` |
| **Pledge Editor** | `PledgeEditor.php` | Legacy | ⚠️ Legacy |
| **Currency display** | SystemConfig | Epic #8459 in progress | 🔧 Partial i18n |

---

### 3.9 Fundraiser Module

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **Dashboard** | `FindFundRaiser.php` | Session `iCurrentFundraiser` | ✅ `standard.fundraiser.spec.js` |
| **Create Fundraiser** | `FundRaiserEditor.php` | `fundraiser_fr` table | ✅ |
| **Add Donors** | `AddDonors.php` | Buyer list | ✅ |
| **View Buyers** | `PaddleNumList.php` | Paddle numbers | ✅ |
| **Batch winner entry** | `BatchWinnerEntry.php` | Legacy | ⚠️ Limited test coverage |
| **Donated items** | `DonatedItemEditor.php` | Auction items | ⚠️ Legacy — minimal Cypress |

---

### 3.10 Data / Reports / Query

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **Query Menu** | `QueryList.php` | Saved queries `query_qry` | ✅ `admin.query-list.spec.js` |
| **Query View** | `QueryView.php` | Dynamic SQL from saved queries | ⚠️ **Security-sensitive** — admin gated |
| **Directory Reports** | `DirectoryReports.php` | Label generation | ⚠️ Legacy |
| **Confirm Report** | `Reports/ConfirmReport.php` | Membership confirmation | ✅ `confirm-report-null-fix.spec.js` |
| **Advanced Deposit** | `Reports/AdvancedDeposit.php` | Financial | ⚠️ Legacy |
| **PDF reports** | `Reports/*.php` (24 files) | FPDF + iconv | ⚠️ — ✅ `pdf-report-iconv-issue.spec.js` |
| **CSV Export** | `CSVExport.php` | League CSV | ✅ Admin-restricted 7.3.3+ |
| **CSV Create** | `CSVCreateFile.php` | Export builder | ⚠️ Legacy |
| **GeoPage** | `GeoPage.php` | Map density | ⚠️ Legacy Leaflet bridge |

---

### 3.11 Admin Module

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **Admin Dashboard** | `/admin/` | `AdminService` warnings | ✅ `admin.dashboard.spec.js` |
| **Church Information** | `/admin/system/church-info` | Consolidated settings (#8383) | ✅ `admin.church-info.spec.js` |
| **Get Started wizard** | `/admin/get-started` | Onboarding checklist (#8295) | ✅ new-system specs |
| **System Users** | `/admin/system/users` | `UserService` | ✅ `admin.user.spec.js`, `admin.user-editor.spec.js` |
| **System Settings** | `SystemSettings.php` | Legacy + `system-settings-panel` JS | ⚠️ Mixed legacy/MVC |
| **Plugins** | `/plugins/management` | `PluginManager` | ✅ `ui/plugins/` specs |
| **Export hub** | `/admin/export` | CSV + backup + ChMeetings (#8475) | ✅ `admin.export.spec.js` |
| **Backup** | Admin API + UI | `ifsnop/mysqldump-php` | ✅ `admin.backup.spec.js`, new-system `03-backup-restore` |
| **Restore** | Admin UI | Upload + execute | ✅ `admin.restore.spec.js` |
| **Upgrade wizard** | Admin API | `UpgradeService`, GitHub releases | ✅ `system-upgrade.spec.js` |
| **System reset** | Admin API | Destructive — new-system `04-system-reset` | ✅ |
| **Demo import** | Admin API | `DemoDataService` | ✅ new-system `02-demo-import` |
| **Orphaned files** | Admin API | `AppIntegrityService` | ✅ `admin.orphaned-files.spec.js` |
| **System logs** | Admin API | Monolog log files | ✅ admin logs specs |
| **Setup wizard** | `/setup/` | Pre-Config.php install | ✅ new-system `01-setup-wizard` |
| **Integrity check** | `AppIntegrityService` | signatures.json (957 files) | ✅ |
| **Issue reporter** | API `system-issues` | GitHub issue filing | ✅ |

---

### 3.12 Session & Security

| Feature | Route | Implementation | Status |
|---------|-------|----------------|--------|
| **Login** | `/session/begin` | `AuthenticationManager` | ✅ `guest.login.errors.spec.js` |
| **Logout** | `/session/end` | Session destroy | ✅ |
| **2FA enrollment** | `/session/two-factor` | Google2FA + QR | ✅ `session.two-factor-recovery.spec.js` |
| **Password reset** | `/session/password-reset` | Token email flow | ✅ `session.password-reset.spec.js` |
| **Forced password change** | `/changepassword` | `NeedPasswordChange` flag | ✅ new-system spec |
| **Role-limited access** | Middleware | Per-route auth | ✅ `limited-access.spec.js` |
| **Redirect safety** | `RedirectUtils` | No open redirects | ✅ `redirect-utils.spec.js` |
| **CSP** | Response headers | Report-only + nonce scripts | ✅ Hardened 7.1.1 |
| **API 401/403** | `AuthMiddleware` | JSON errors | ✅ API auth specs |

---

### 3.13 Plugins (Core)

| Plugin | Purpose | Status |
|--------|---------|--------|
| **gravatar** | Avatar URLs by email | ✅ Working |
| **google-analytics** | GA4 tracking | ✅ Disable test #8421 |
| **mailchimp** | List sync | ⚠️ Needs API key |
| **vonage** | SMS | ⚠️ Needs credentials |
| **openlp** | Presentation control | ⚠️ Needs OpenLP endpoint |
| **external-backup** | WebDAV backup | ⚠️ Needs WebDAV server |
| **custom-links** | Sidebar menu links | ✅ |
| **holidays** | Yasumi holiday calendar | ✅ `holidays.spec.js` |

---

### 3.14 External / Public

| Feature | Route | Status |
|---------|-------|--------|
| **Public calendar** | `/external/calendar` | ✅ `external.calendar.spec.js` |
| **Family register** | `/external/register` | ✅ `guest.family.reg.spec.js` |
| **Email verify** | `/external/verify` | ✅ |
| **Public API** | `/api/public/*` | ✅ 5 public API specs |
| **CSP report** | `/api/public/csp-report` | ✅ |

---

### 3.15 Localization

| Aspect | Detail |
|--------|--------|
| **Locales** | 46+ active (`locale/i18n/*.json`) |
| **Workflow** | POEditor → `locale/messages.po` → `npm run locale:build` |
| **PHP** | `gettext()` |
| **JS** | `i18next` via `locale-loader` bundle |
| **RTL** | `churchcrm-rtl.min.css` separate bundle |
| **Status** | ✅ Audit in `locale/poeditor-audit.md` |

---

### 3.16 Legacy Pages Still at `src/*.php` (57 files)

These bypass Slim MVC and are routed by `src/index.php` → `CamelCase.php`:

`AddDonors.php`, `BatchWinnerEntry.php`, `CartToFamily.php`, `CartToGroup.php`, `ConvertIndividualToFamily.php`, `CSVCreateFile.php`, `CSVExport.php`, `DepositSlipEditor.php`, `DirectoryReports.php`, `DonatedItem*.php`, `DonationFundEditor.php`, `FamilyCustomFields*.php`, `FamilyEditor.php`, `FinancialReports.php`, `FindDepositSlip.php`, `FindFundRaiser.php`, `FundRaiser*.php`, `GeoPage.php`, `GroupEditor.php`, `GroupPropsEditor.php`, `ManageEnvelopes.php`, `MemberRoleChange.php`, `OptionManager.php`, `PersonCustomFields*.php`, `PersonEditor.php`, `PledgeEditor.php`, `Property*.php`, `Query*.php`, `SystemSettings.php`, `UserEditor.php`, `VolunteerOpportunityEditor.php`, etc.

**Status:** ⚠️ **Working but technical debt** — targeted for MVC migration per epic [#8301](https://github.com/ChurchCRM/CRM/issues/8301). Each legacy page is a separate migration unit.

---

## 4. Known Issues & Technical Debt

### 4.1 Security (Historical CVEs — Patched in Current)

| GHSA | Issue | Fixed in |
|------|-------|----------|
| GHSA-wxcc-gvfv-56fg, GHSA-qc2c-qmw4-52fp | SQL injection (critical) | 6.4.0 |
| GHSA-p3q7-q68q-h2gr | SQL injection | 6.7.1 |
| GHSA-49qp-cfqx-c767, GHSA-8r36-fvxj-26qv | Stored XSS (calendar, fundraiser, properties) | 6.7.1–6.8.1 |
| GHSA-j9gv-26c7-3qrh | Stored XSS | 7.1.1 |
| GHSA-3xq9-c86x-cwpp | CSRF | 7.2.2 |
| GHSA-4vj2-gm78-3q63 | CSV export auth bypass | 7.4.0 (#8927) |
| GHSA-mp2w-4q3r-ppx7 | Config file web access | 7.3.x (#8869) |

**Current npm audit:** 3 moderate severity vulnerabilities reported at last `npm ci` (run `npm audit` for details). Production overrides resolve many transitive issues.

### 4.2 Active Technical Debt

| Item | Severity | Detail |
|------|----------|--------|
| **Dual UI stack** | Medium | 57 legacy root PHP pages + 13 Slim apps coexist; inconsistent patterns |
| **Tabler migration incomplete** | Medium | Epic #8301; some pages still AdminLTE-era logic with Tabler skin |
| **Dual build pipeline** | Low | Grunt + Webpack + Composer + Perpl generation — long `npm run build` |
| **QueryView dynamic SQL** | High (mitigated) | Saved queries execute dynamic SQL — admin-only; historical SQLi vector |
| **Mixed finance UI** | Medium | `/finance/` MVC + `DepositSlipEditor.php` legacy |
| **SystemSettings.php legacy** | Low | Not fully migrated to `system-settings-panel` |
| **Docker image not published** | Low | `churchcrm/crm:php8-debian-dev` manifest not on Docker Hub — must `--build` locally |
| **7.7 GB live USB constraint** | Ops | Docker builds fail with "no space left on device" on small overlays |
| **Seed SQL view import** | Low | `DEFINER` views in `cypress/data/seed.sql` fail on some MariaDB grants (non-fatal) |
| **Deprecated APIs in code** | Low | `SlimUtils` Slim 3 method, `DonationFundService::getAll()` predecessor, `SystemConfig` Vonage/OpenLP helpers → use PluginManager |
| **Functions.php** | Resolved | Removed 7.1.0 — grep may still find references in old docs |
| **Bootstrap 4 remnants** | Low | Cleanup ongoing (#8844); skill docs warn against BS4 classes |
| **Currency localization** | In progress | Epic #8459 — not all finance surfaces localized |
| **Community plugin security** | Medium | Runtime-installed plugins bypass core review — `plugin-security-scan` skill |
| **Demo site** | Ops | `churchcrm.io/demo.html` is shared read-write — not for production data |
| **Subdirectory installs** | Medium | Requires `$sRootPath` + nginx/Caddy prefix routing (#8895 fixed redirects) |
| **Plugin settings migration** | Ops | Upgrading from \<7.0 requires re-entering MailChimp/Vonage/OpenLP credentials in Plugin Manager |

### 4.3 Performance Bottlenecks

| Area | Detail |
|------|--------|
| **Large webpack bundles** | `churchcrm.min.js` ~979KB, `churchcrm.min.css` ~974KB — performance warnings in build |
| **N+1 queries** | Sunday School cleanup (#8343) addressed some; legacy pages may still N+1 |
| **Tax report memory** | Fixed 6.7.1 — large congregations were OOM |
| **Kiosk polling** | Reduced to 60s smart-refresh in 7.3.1 |
| **Opcache + upgrades** | 6.5.4 fix — stale opcode cache breaking upgrades |

### 4.4 Data Unavailable

| Item | Reason |
|------|--------|
| **Exact PHPUnit unit test count** | Project uses Cypress E2E as primary gate; no comprehensive PHPUnit suite inventory |
| **Production deployment count** | Not tracked in repo |
| **Complete list of open GitHub issues** | `gh` CLI unavailable in forensic environment |
| **Per-feature code coverage %** | No Istanbul/coverage report in CI for PHP or JS |
| **Original author names pre-2015** | Early CVS commits lack descriptive messages |
| **ChurchInfo → ChurchCRM exact rename commit** | Data Unavailable — branding transition spans multiple years |

---

## 5. Conception to Current State

### Phase 1: ChurchInfo (2004–2014)

**Problem statement:** Churches needed free software to track members, donations, and deposits without expensive ChMS licenses.

**Solution shape:** PHP scripts per screen, MySQL schema centered on `person_per`, `family_fam`, `pledge_plg`, `deposit_dep`. MICR check reading for deposit entry. Report classes shared between directory and financial reporting.

**Key inheritance still visible:** Table naming (`*_per`, `*_fam`, `*_plg`), deposit slip session (`$_SESSION['iCurrentDeposit']`), envelope numbers, fundraiser paddle numbers.

### Phase 2: Early ChurchCRM (2015–2022)

**Problem evolution:** Web-based access, multi-user roles, email integration, calendar, groups, Sunday School.

**Architectural additions:** User roles in `user_usr`, API beginnings, Composer adoption, Propel ORM introduction, AdminLTE UI, GitHub/open-source community.

### Phase 3: Security & Modernization Push (2023–2025)

**Catalyst:** Critical SQLi in 6.4.0 (Dec 2025) forced security audit and ORM enforcement.

**Changes:** Cypress security suites, CSRF middleware, XSS escaping via `InputUtils`, `RedirectUtils`, admin MVC for upgrades (#6.0.0), finance module extraction (#6.3.0).

### Phase 4: 7.0 Foundation (Feb 2026)

**Problem:** Integrations tightly coupled; PHP EOL; maps cost money; no extension model.

**Delivered:** Plugin system, PHP 8.4, Leaflet, 10th-anniversary release. Breaking: re-enter plugin credentials after upgrade.

### Phase 5: 7.1 UI Revolution (Apr 2026)

**Problem:** AdminLTE looked dated; Bootstrap 4 blocked modern components; mobile UX poor.

**Delivered:** Tabler + BS5 everywhere, `Functions.php` deleted, Notes API, CSV import modernization, 46 locales, session hardening (fixation, cookie flags), 200+ commits.

**Rejected:** Keeping React — removed in 7.1.2 for simpler jQuery/TS maintenance.

### Phase 6: 7.2–7.4 Refinement (Apr–Jun 2026)

**Focus:** Event MVC epic, timezone wall-clock correctness (#8806), kiosk UX, person/family MVC views (#8479), note timeline (#8856), subdirectory routing (#8895), CSV export lockdown (#8927), OpenAPI v6 refactor (#8881), 40-locale AI translation (#8921).

**Current state (7.4.0):** Hybrid architecture — 13 Slim apps, 57 legacy pages, 22 services, 161 Cypress specs, 8 core plugins, 46 DB tables, Tabler UI with ongoing legacy bridge.

---

## 6. Quick Start (Developer)

### Requirements

| Tool | Version |
|------|---------|
| PHP | 8.4+ with extensions per `composer.json` |
| Node | 24+ (`.nvmrc`) |
| Composer | 2.x |
| MariaDB/MySQL | 10.11+ |
| Docker | Optional (recommended for Cypress) |

### Fastest local path

```bash
git clone <repo-url> CRM && cd CRM
npm ci
npm run build                    # Composer + Webpack + Grunt + signatures

# Docker (recommended):
cp docker/Config.php src/Include/Config.php
npm run docker:dev:start         # First run: docker compose --profile dev up -d --build
# http://localhost — admin / changeme

# Or DDEV:
ddev start && ddev setup-churchcrm && ddev launch

# Or native Apache + MariaDB (low-disk environments):
# See docker/Config.php — set $sSERVERNAME = 'localhost'
# Import cypress/data/seed.sql for demo data
```

### Key commands

| Command | Purpose |
|---------|---------|
| `npm run build` | Full production build |
| `npm run build:frontend` | JS/CSS only |
| `npm run build:php` | Composer + syntax validate |
| `npm run test` | All Cypress (requires Docker) |
| `npm run test:api` | API specs only |
| `npm run lint` | Biome check on `webpack/` |
| `npm run locale:build` | Rebuild i18n after `gettext()` changes |

### Default credentials (demo seed)

- **User:** `admin`
- **Password:** `changeme`

---

## 7. Community & Contribution

ChurchCRM exists to serve the Church — open-source, community-built, freely given.

| Resource | Link |
|----------|------|
| **User documentation** | [docs.churchcrm.io](https://docs.churchcrm.io) |
| **Contributing** | [CONTRIBUTING.md](CONTRIBUTING.md) |
| **Security** | [SECURITY.md](SECURITY.md) — report via GitHub Security Advisories, not public issues |
| **Discord** | [discord.gg/tuWyFzj3Nj](https://discord.gg/tuWyFzj3Nj) |
| **Localization** | [POEditor project](https://poeditor.com/join/project/RABdnDSqAt) |
| **Demo** | [churchcrm.io/demo.html](https://churchcrm.io/demo.html) — shared, reset regularly, sample data only |
| **Releases** | [GitHub Releases](https://github.com/ChurchCRM/CRM/releases) · [CHANGELOG.md](CHANGELOG.md) |

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE)
[![Latest Release](https://img.shields.io/github/v/release/churchcrm/crm?label=Latest%20Release)](https://github.com/ChurchCRM/CRM/releases/latest)
[![GitHub contributors](https://img.shields.io/github/contributors/churchcrm/crm.svg)](https://github.com/ChurchCRM/CRM/graphs/contributors)

**PR requirements:** Link to open issue; Cypress tests for new behavior; `npm run lint` + `npm run build` before commit; feature PRs need sibling docs issue for [docs.churchcrm.io](https://docs.churchcrm.io).

---

*Forensic README last updated: June 2026 · ChurchCRM 7.4.0 · For agent/developer skills see `.agents/skills/churchcrm/SKILL.md` and `CLAUDE.md`.*
