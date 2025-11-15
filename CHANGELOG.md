# Changelog

All notable changes to this project will be documented in this file.

_For archived versions prior to v5.0.0, see the [legacy changelog](https://github.com/ChurchCRM/CRM/releases)._

## [5.22.0] - 2025-11-01
### :sparkles: New Features
- [`0eb12c1`](https://github.com/ChurchCRM/CRM/commit/0eb12c1814ccaa4ee8716358f2552e029a3e46f6) - **auth**: Create AuthService and migrate requireUserGroupMembership *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`dd2b4b7`](https://github.com/ChurchCRM/CRM/commit/dd2b4b776bee27ba13485e01ac9fbbc3d83f71b2) - Improve GroupList UI and navigation *(commit by [@DawoudIO](https://github.com/DawoudIO))*

### :bug: Bug Fixes
- [`2db0d74`](https://github.com/ChurchCRM/CRM/commit/2db0d74529dc367f225ed9c9572400112770f9c9) - upgrade bootstrap-datepicker from 1.10.0 to 1.10.1 *(commit by [@snyk-bot](https://github.com/snyk-bot))*
- [`c8e2f11`](https://github.com/ChurchCRM/CRM/commit/c8e2f116df3fc1126096f373251c53c0ccee2ed5) - upgrade i18n from 0.15.1 to 0.15.2 *(commit by [@snyk-bot](https://github.com/snyk-bot))*
- [`0050014`](https://github.com/ChurchCRM/CRM/commit/00500148d5602bb70bd83392e626d1ffc4595e1a) - Add missing RoutingMiddleware to Slim 4 applications causing post-login 404 errors *(PR [#7480](https://github.com/ChurchCRM/CRM/pull/7480) by [@DawoudIO](https://github.com/DawoudIO))*
  - :arrow_lower_right: *fixes issue [#7478](https://github.com/ChurchCRM/CRM/issues/7478) opened by [@prbt2016](https://github.com/prbt2016)*
- [`8a1fa0f`](https://github.com/ChurchCRM/CRM/commit/8a1fa0f679f6bd1ad58e6f5d611bd6a42926d22c) - Group-specific properties not creating records when adding members to groups *(PR [#7439](https://github.com/ChurchCRM/CRM/pull/7439) by [@DawoudIO](https://github.com/DawoudIO))*
  - :arrow_lower_right: *fixes issue [#7388](https://github.com/ChurchCRM/CRM/issues/7388) opened by [@kojakrm](https://github.com/kojakrm)*
- [`7daf445`](https://github.com/ChurchCRM/CRM/commit/7daf445d5630d0da9476be667b0f78add0581c77) - **finance**: Fix payment type mismatch and add fiscal year formatting *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`7b95c16`](https://github.com/ChurchCRM/CRM/commit/7b95c161f99c623c0d9b2e6c43bafcb42d6ab8e2) - **test**: Use common Cypress utility for all API calls in finance tests *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`dd88ce3`](https://github.com/ChurchCRM/CRM/commit/dd88ce3bac6a0208be4314f3fa2701e562336060) - **api**: Add null safety check for notification title in system API *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`03161dd`](https://github.com/ChurchCRM/CRM/commit/03161dde577793127cfbfc188de4dad9842e3cd5) - **api**: Add null safety and make notification endpoint configurable *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`f7cb701`](https://github.com/ChurchCRM/CRM/commit/f7cb70113d2093fdd995b5c4bfb2d01e50cb6bea) - **api**: Correct UiNotification constructor call - use separate placement/align parameters *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`54e5903`](https://github.com/ChurchCRM/CRM/commit/54e59030c889386a79000e20d63daea9fb7cd617) - **test**: Fix Cypress API command to return full response object *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`ad77d5a`](https://github.com/ChurchCRM/CRM/commit/ad77d5a932bccff43e04650c7bbd4041b6bfe7d0) - **test**: Update finance payments tests to use resp.body for API response body *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`1bb919c`](https://github.com/ChurchCRM/CRM/commit/1bb919c845a42fcc282d4d1a65ab1cd7193db340) - **ui**: Reorganize family view buttons into logical groups with improved layout *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`c2f49e7`](https://github.com/ChurchCRM/CRM/commit/c2f49e799fd768dff1af611658d28c7c9fa24fe4) - Consume tokens after validation to prevent reuse *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`485c362`](https://github.com/ChurchCRM/CRM/commit/485c362aecaadb741a1158c3cc7415fce364158d) - Consume tokens after validation to prevent reuse *(PR [#7503](https://github.com/ChurchCRM/CRM/pull/7503) by [@DawoudIO](https://github.com/DawoudIO))*

### :recycle: Refactors
- [`2ecb22d`](https://github.com/ChurchCRM/CRM/commit/2ecb22dff2a820b1845444d128ff9ac9ec1652a1) - Move SCSS compilation to both skin-main and skin-loggedout entries *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`46ad011`](https://github.com/ChurchCRM/CRM/commit/46ad011801d39b4c4f8bbfd1cf3882d373afc80a) - Remove old window.CRM.cart API, use CartManager directly *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`6e19673`](https://github.com/ChurchCRM/CRM/commit/6e19673ebd0e67f206621960445106808f15d2d2) - Remove group type dropdown filter from GroupList *(commit by [@DawoudIO](https://github.com/DawoudIO))*
- [`d849ab5`](https://github.com/ChurchCRM/CRM/commit/d849ab50f2c5a12a249ebce1ddeba9d7fd00d034) - Reorganize styles and add development guidelines *(commit by [@DawoudIO](https://github.com/DawoudIO))*

### :white_check_mark: Tests
- [`0d8ee97`](https://github.com/ChurchCRM/CRM/commit/0d8ee97dcaa7a7deb692192d2697795d6bc7fdd8) - **finance**: Add real-world test cases for payment API type fix *(commit by [@DawoudIO](https://github.com/DawoudIO))*


## [5.21.0](https://github.com/ChurchCRM/CRM/releases/tag/5.21.0) - 2025-10-20

<!-- Release notes generated using configuration in .github/release.yml at cf70b742f3c8a5177ba96f098ac9c96f04567c5b -->

### 🎉 Exciting New Features
* Add Admin-only option to view and delete system logs by @Copilot in https://github.com/ChurchCRM/CRM/pull/7437
* Add admin task and management page for log file cleanup by @Copilot in https://github.com/ChurchCRM/CRM/pull/7410

### 🔑  Security 
* App Bootstrap cleanup and security updates by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7461
* Security: Sanitize User Input in API Endpoints by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7467
* Fix CVE-2019-17205: Stored XSS vulnerability in deposit comments by @Copilot in https://github.com/ChurchCRM/CRM/pull/7431

### 🪲 Bugs
* Fix CSV import bug: correct inverted date validation logic in ParseDate function by @Copilot in https://github.com/ChurchCRM/CRM/pull/7440
* Fix Config.php writability check failing during initial setup by @Copilot in https://github.com/ChurchCRM/CRM/pull/7426
* Fix TypeError in AppIntegrityService::getIntegrityCheckMessage() after upgrade to 5.12.0 by @Copilot in https://github.com/ChurchCRM/CRM/pull/7425
* Fix: Birthday calendar filter to use proper integer comparison by @Copilot in https://github.com/ChurchCRM/CRM/pull/7429
* Fix logging timezone consistency by setting UTC default before bootstrap by @Copilot in https://github.com/ChurchCRM/CRM/pull/7412
* Fix missing directories in backup when bBackupExtraneousImages is False by @Copilot in https://github.com/ChurchCRM/CRM/pull/7418
* Fix backup database error by adding proper directory creation error handling by @Copilot in https://github.com/ChurchCRM/CRM/pull/7417
* Fix: Calendar deletion and access token update bugs by @Copilot in https://github.com/ChurchCRM/CRM/pull/7383

### 💬 Localization
* 5.20.0 POEditor Update - 2025-10-06 by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7385
* 5.19.0 POEditor Update - 2025-10-11 by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7393
* Locale: Better Scripts & KO Locale  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7397
* 🌍 POEditor Locale Update - Download KO-KR by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7399
* 🌍 POEditor Locale Update - 2025-10-12 by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7401
* Updated locale scripts by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7402
* 🌍 POEditor Locale Update - 2025-10-13 by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7453


### Inner Beauty
* started 5.20.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7395
* Fix unnecessary exception logging for public API authentication checks by @Copilot in https://github.com/ChurchCRM/CRM/pull/7415
* Slim MVC - Ensure all code is compatible with Slim v4 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7465
* New DepositService - SQL to ORM  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7466
* Upgrade Cypress System and Test to match latest recommendations  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7384
* Docker cleanup / speed up by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7386
* Update build-test-package.yml  to modern actions  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7398
* Cleanner e2e upgrade script with no manual changes by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7405
* cleanupLocalGit is not a needed via Grunt by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7407
* fixed 2 versions of cypress and upgraded to latest version by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7406
* remove babel as it is not used by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7408
* Build - Starting release 5.21.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7444
* Potential fix for code scanning alert no. 142: Workflow does not contain permissions by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7459
* Potential fix for code scanning alert no. 139: Workflow does not contain permissions by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7460
* Move upgrade routines from SystemService to new VersionUtils utility class by @Copilot in https://github.com/ChurchCRM/CRM/pull/7414
* Slim cleanup by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7462
* Add missing PHP extension requirements for intl, bcmath, and sodium by @Copilot in https://github.com/ChurchCRM/CRM/pull/7394
* removed grunt-lineending by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7400
* Fix version detection error when already on latest release by @Copilot in https://github.com/ChurchCRM/CRM/pull/7411


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.19.0...5.21.0

## [5.19.0](https://github.com/ChurchCRM/CRM/releases/tag/5.19.0) - 2025-10-06

<!-- Release notes generated using configuration in .github/release.yml at master -->

## 🚀 Highlights

### ✨ Major Changes

**Setup Wizard Improvements**
- Security: Hardened setup process to prevent re-running after installation and improved input validation.
- UI/UX: Improved setup wizard with clearer field validation and inline help.
- Inline help and validation for Root Path, Base URL, and database fields.
- HTML5 and Bootstrap validation for a more user-friendly experience.
- All setup fields are validated and sanitized on both frontend and backend to prevent code injection and misconfiguration.

**Email Debug System Refactor**
- Debug email route now uses the same code as production emails.

## 🙏 Thanks
Thanks to all contributors and users for feedback and bug reports!


## What's Changed
### 🪲 Bugs
* 5.19 UI bugs by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7367
* Fix API Authentication Bypass by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7376
* Bug: Login / Password UI fixes  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7380
* Fix SMTP test connection to include security settings (TLS/SSL) by @Copilot in https://github.com/ChurchCRM/CRM/pull/7375
### 💬 Localization
* 5.19.0 POEditor Update - 2025-09-10 by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7368
* 5.19.0 POEditor Update - 2025-09-20 by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7373
### Inner Beauty
* Starting 5.19 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7360
* No longer building tar and demo files  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7362
* Better use of npm prettier by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7363
* Build: SASS build cleanup by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7364
* Removed grunt-contrib-clean by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7365
* Potential fix for code scanning alert no. 126: Workflow does not contain permissions by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7379
### Other Changes
* [Snyk] Upgrade react-datepicker from 8.4.0 to 8.7.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7371
* [Snyk] Upgrade fullcalendar from 6.1.18 to 6.1.19 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7369
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7377
* Security: Setup input filer and setup instrction updates by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7378
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7382

## New Contributors
* @Copilot made their first contribution in https://github.com/ChurchCRM/CRM/pull/7375

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.18.0...5.19.0

## [5.18.0](https://github.com/ChurchCRM/CRM/releases/tag/5.18.0) - 2025-09-01

<!-- Release notes generated using configuration in .github/release.yml at 8a7b0efc346bb6f494fc4728001dec4d1a0c3e44 -->

## What's Changed
### 🪲 Bugs
* Fix: Birthday calendar API now filters by start and end parameters by @btdn in https://github.com/ChurchCRM/CRM/pull/7335
* Fix logoff url by @mounte in https://github.com/ChurchCRM/CRM/pull/7354
* fix: simplify logic to validate redirect by @vitormattos in https://github.com/ChurchCRM/CRM/pull/7356
### 💬 Localization
* 5.18.0 POEditor Update - 2025-07-08 by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7344
* 5.18.0 POEditor Update - 2025-08-31 by @github-actions[bot] in https://github.com/ChurchCRM/CRM/pull/7352

### 👒 Dependencies
* Bump undici from 6.21.1 to 6.21.3 by @dependabot[bot] in https://github.com/ChurchCRM/CRM/pull/7324
* [Snyk] Upgrade react-datepicker from 8.3.0 to 8.4.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7340
* [Snyk] Upgrade bootbox from 6.0.3 to 6.0.4 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7339
* [Snyk] Upgrade flag-icons from 7.3.2 to 7.5.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7338
* [Snyk] Upgrade react-bootstrap from 2.10.9 to 2.10.10 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7331
* Auto lib upgrade testing by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7343
* Bump sweetalert2 from 11.14.5 to 11.22.4 by @dependabot[bot] in https://github.com/ChurchCRM/CRM/pull/7353
* [Snyk] Upgrade react-select from 5.10.1 to 5.10.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7350
* Bump tmp from 0.2.3 to 0.2.4 by @dependabot[bot] in https://github.com/ChurchCRM/CRM/pull/7351
* [Snyk] Upgrade chart.js from 4.4.9 to 4.5.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7346
* [Snyk] Upgrade fullcalendar from 6.1.17 to 6.1.18 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7347

## New Contributors
* @mounte made their first contribution in https://github.com/ChurchCRM/CRM/pull/7354
* @vitormattos made their first contribution in https://github.com/ChurchCRM/CRM/pull/7356

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.17.0...5.18.0

## [5.17.0](https://github.com/ChurchCRM/CRM/releases/tag/5.17.0) - 2025-05-11

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed

### 🛠 Breaking Changes
* Locale: Update Locale code for CS & JP by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7321
* Must reset locale setting to address ^

### 💬 Localization
* 5.17.0 POEditor Update - 2025-05-09 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7318
* 5.17.0 POEditor Update - 2025-05-10 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7319

### 👒 Dependencies
* [Snyk] Upgrade react-datepicker from 8.1.0 to 8.2.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7302
* [Snyk] Upgrade i18next from 24.2.2 to 24.2.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7306
* [Snyk] Upgrade react-datepicker from 8.2.0 to 8.2.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7307
* [Snyk] Upgrade react-datepicker from 8.2.1 to 8.3.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7314
* [Snyk] Upgrade fullcalendar from 6.1.15 to 6.1.17 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7313
* [Snyk] Upgrade bootbox from 6.0.0 to 6.0.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7312
* [Snyk] Upgrade bootbox from 6.0.2 to 6.0.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7315
* [Snyk] Upgrade chart.js from 4.4.8 to 4.4.9 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7316
* using DROP COLUMN IF EXISTS for Upgrades  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7320


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.16.0...5.17.0

## [5.16.0](https://github.com/ChurchCRM/CRM/releases/tag/5.16.0) - 2025-03-29

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed

### 🪲 Bugs
* Bug: Deposit tracking on the dashboard not visible by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7293

### 💬 Localization
* 5.16.0 POEditor Update - 2025-03-09 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7284
* 5.16.0 POEditor Update - 2025-03-23 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7295
* 5.16.0 POEditor Update - 2025-03-24 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7296
* 5.16.0 POEditor Update - 2025-03-29 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7300

### Inner Beauty
* Starting 5.16.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7282
* Addressed Docker Warning by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7283

### 👒 Dependencies
* [Snyk] Upgrade chart.js from 4.4.7 to 4.4.8 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7291
* [Snyk] Upgrade react-datepicker from 8.0.0 to 8.1.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7288
* [Snyk] Upgrade react-select from 5.10.0 to 5.10.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7297

### Other Changes
* Church Info upgrade issues  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7294
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7298

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.15.0...5.16.0

## [5.15.0](https://github.com/ChurchCRM/CRM/releases/tag/5.15.0) - 2025-03-08

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed
### 🎉 Exciting New Features
* UI Cleanup & UI Bugs by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7274
* Fundraiser UI Update and ORM Convertion  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7276
### 🪲 Bugs
* Bug: WhyCome does not save by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7275
### 💬 Localization
* 5.15.0 POEditor Update - 2025-02-27 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7277
* 5.15.0 POEditor Update - 2025-03-04 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7279
* 5.15.0 POEditor Update - 2025-03-05 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7280
### Inner Beauty
* SASS Dev cleanup by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7273
* Update Locale Action to run audit post download by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7278

### 👒 Dependencies
* [Snyk] Upgrade ckeditor4 from 4.25.0 to 4.25.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7281


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.14.0...5.15.0

## [5.14.0](https://github.com/ChurchCRM/CRM/releases/tag/5.14.0) - 2025-02-22

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed
### 🪲 Bugs
* Fix Calendar Properties URLs Are Missing a Slash by @Moonlight567 in https://github.com/ChurchCRM/CRM/pull/7235
* Only display error details if we are in debug more by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7243
* Security: username url xss by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7244

### 💬 Localization
* 5.14.0 POEditor Update - 2025-01-16 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7228
* 5.14.0 POEditor Update - 2025-02-02 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7239
* 5.14.0 POEditor Update - 2025-02-19 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7259
* 5.14.0 POEditor Update - 2025-02-20 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7262
* 5.14.0 POEditor Update - 2025-02-22 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7264

### 👒 Dependencies
* [Snyk] Upgrade i18next from 24.1.2 to 24.2.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7226
* [Snyk] Upgrade i18next from 24.2.1 to 24.2.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7260
* [Snyk] Upgrade react-datepicker from 7.5.0 to 7.6.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7237
* [Snyk] Upgrade i18next from 24.2.0 to 24.2.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7236
* [Snyk] Upgrade flag-icons from 7.2.3 to 7.3.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7256
* Bump undici from 6.20.1 to 6.21.1 by @dependabot in https://github.com/ChurchCRM/CRM/pull/7232
* Bump twig/twig from 3.17.1 to 3.19.0 in /src by @dependabot in https://github.com/ChurchCRM/CRM/pull/7238
* Bump esbuild and i18next-parser by @dependabot in https://github.com/ChurchCRM/CRM/pull/7261

## New Contributors
* @Moonlight567 made their first contribution in https://github.com/ChurchCRM/CRM/pull/7235

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.13.0...5.14.0

## [5.13.0](https://github.com/ChurchCRM/CRM/releases/tag/5.13.0) - 2025-01-16

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed
### 🪲 Bugs
* Cleanner Email / Debug  Error messages by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7204
* Fixed Base Email template path + new test for user create by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7201
### 💬 Localization
* 5.13.0 POEditor Update - 2025-01-06 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7224
### Other Changes
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7206
* [Snyk] Upgrade i18next from 23.16.5 to 23.16.8 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7212
* update all semver-safe dependencies by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7216


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.12.0...5.13.0

## [5.12.0](https://github.com/ChurchCRM/CRM/releases/tag/5.12.0) - 2024-11-09

<!-- Release notes generated using configuration in .github/release.yml at 1de975803207a6ff8b3b4f84ebefe5b7252f9247 -->

## What's Changed
### 🎉 Exciting New Features
* New Locale - Telugu by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7194

### 🪲 Bugs
* fix - removed second header from event attendance page by @etopipec in https://github.com/ChurchCRM/CRM/pull/7183
* improve grammar for reset password email by @romdricks in https://github.com/ChurchCRM/CRM/pull/7173

### 💬 Localization
* 5.12.0 POEditor Update - 2024-10-04 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7176
* 5.12.0 POEditor Update - 2024-10-05 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7177
* 5.12.0 POEditor Update - 2024-10-06 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7178
* 5.12.0 POEditor Update - 2024-10-07 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7179
* 5.12.0 POEditor Update - 2024-10-09 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7180
* 5.12.0 POEditor Update - 2024-10-10 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7181
* 5.12.0 POEditor Update - 2024-10-16 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7187

### 👒 Dependencies
* Bump uplot from 1.6.30 to 1.6.31 by @dependabot in https://github.com/ChurchCRM/CRM/pull/7175
* [Snyk] Upgrade react-datepicker from 7.3.0 to 7.4.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7184
* update deps by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7188
* Bump twig/twig from 3.14.0 to 3.14.1 in /src by @dependabot in https://github.com/ChurchCRM/CRM/pull/7193

### Other Changes
* update wiki link by @mjones129 in https://github.com/ChurchCRM/CRM/pull/7174

## New Contributors
* @mjones129 made their first contribution in https://github.com/ChurchCRM/CRM/pull/7174
* @etopipec made their first contribution in https://github.com/ChurchCRM/CRM/pull/7183

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.11.0...5.12.0

## [5.11.0](https://github.com/ChurchCRM/CRM/releases/tag/5.11.0) - 2024-09-28

<!-- Release notes generated using configuration in .github/release.yml at 1b3950d9c50e7c0990a99ef9e3d95a4dbad9163f -->

## What's Changed
### 🎉 Exciting New Updates
* Configurable Person's Initial Format by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7141
* Use iPersonNameStyle in GroupView by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7146
* Make Person List Columns Configurable by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7147
* Make Family List Columns Configurable by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7149 https://github.com/ChurchCRM/CRM/pull/7152
* Make initial font configurable by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7136
* Address by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7151
* Order by most recent events on checkin by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7159
* 
### 🪲 Bugs
* Bug fix: Fix DataTable pageLength value by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7169
* Bug fix: Fix DataTable options of GroupView by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7170
* Bug fix: Number of Groups in Group list doesn't match with Groups cou… by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7168
* Bug: Family Editor reset Classification of Family Members by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7120
* Fix Dashboard Counts by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7123
* Bug: Cannot delete some family information by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7126
* Bugfix: Cannot update Country by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7150
* Fix calendar link by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7153

### 💬 Localization
* 5.10.1 POEditor Update - 2024-09-09 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7138
* 5.10.1 POEditor Update - 2024-09-10 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7140
* 5.10.1 POEditor Update - 2024-09-11 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7142
* 5.10.1 POEditor Update - 2024-09-12 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7143
* 5.10.1 POEditor Update - 2024-09-16 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7145
* 5.10.1 POEditor Update - 2024-09-17 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7148
* 5.10.1 POEditor Update - 2024-09-21 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7154

### Inner Beauty
* initialize $sRowClass so AlternateRowStyle works by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7158
* attempt to resolve reported http 500s in ManageEnvelopes and DonationFundEditor by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7164
* Less `extract()`, addtl code cleanup, update deps by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7137
* make install script more generic + more type safety by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7135
* Stronger PHP types, and update PHP devs by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7155
* fix typos and inaccurate filenames by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7160
* phpcs cleanup by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7163
* Updated MailChimp API key URLs to the new link by @Deepakchowdavarapu in https://github.com/ChurchCRM/CRM/pull/7167


### 👒 Dependencies
* Bump webpack from 5.93.0 to 5.94.0 by @dependabot in https://github.com/ChurchCRM/CRM/pull/7125
* [Snyk] Upgrade i18next from 23.12.2 to 23.12.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7129
* [Snyk] Upgrade i18next from 23.12.3 to 23.12.6 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7132
* [Snyk] Upgrade i18next from 23.12.6 to 23.13.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7133
* Bump twig/twig from 3.12.0 to 3.14.0 in /src by @dependabot in https://github.com/ChurchCRM/CRM/pull/7139

### Other Changes
* Update author info by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7128


## New Contributors
* @Deepakchowdavarapu made their first contribution in https://github.com/ChurchCRM/CRM/pull/7167

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.10.0...5.11.0

## [5.10.0](https://github.com/ChurchCRM/CRM/releases/tag/5.10.0) - 2024-08-25

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed


### 💬 Localization
* Added English - Jamaica & English - South Africa locales  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7090
* POEditor Updates - Join and help us complete our localization -  https://poeditor.com/join/project/RABdnDSqAt 

### 🪲 Bugs
* Remove propel/propel.php from signature file by @grayeul in https://github.com/ChurchCRM/CRM/pull/7098
* Fix a bug in Latest/Updated Date of Family and Person record by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/7116

### Inner Beauty
* [rector] apply SetList::DEAD_CODE, better type checking (see description for breakdown) by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7011

### 👒 Dependencies
* Bump ckeditor4 from 4.24.0 to 4.25.0 by @dependabot in https://github.com/ChurchCRM/CRM/pull/7117
* [Snyk] Upgrade fullcalendar from 6.1.14 to 6.1.15 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7104
* [Snyk] Upgrade i18next from 23.11.5 to 23.12.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7106
* [Snyk] Upgrade jquery-validation from 1.20.1 to 1.21.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7109
* [Snyk] Upgrade @fortawesome/fontawesome-free from 6.5.2 to 6.6.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7110
* [Snyk] Upgrade i18next from 23.12.1 to 23.12.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7114



**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.9.3...5.10.0

## [5.9.3](https://github.com/ChurchCRM/CRM/releases/tag/5.9.3) - 2024-07-13

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed
### 🎉 Exciting New Features
* Created states for countries 

### 🪲 Bugs
* Sanitize family registration form data by @respencer in https://github.com/ChurchCRM/CRM/pull/7063
* Fix awkwardly translated string by @respencer in https://github.com/ChurchCRM/CRM/pull/7048
* ensure PSR request does not get overwritten when logging in by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7052
* Keep counter Totals first for Event Types by @respencer in https://github.com/ChurchCRM/CRM/pull/7065
* [hotfix] fix deletion to reference actual column name by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7081
* Delete Canvass Automation menu entry by @respencer in https://github.com/ChurchCRM/CRM/pull/7085
### 💬 Localization

* Created states for countries (country codes a-c) by @romdricks in https://github.com/ChurchCRM/CRM/pull/7028
* Created states for countries (country codes d-i) by @romdricks in https://github.com/ChurchCRM/CRM/pull/7038
* Created states for countries (country codes j-q) by @romdricks in https://github.com/ChurchCRM/CRM/pull/7042
* Created states for countries (country codes r-z) by @romdricks in https://github.com/ChurchCRM/CRM/pull/7044
* Update Barbados (BB) states by @romdricks in https://github.com/ChurchCRM/CRM/pull/7057

* [Snyk] Upgrade i18next from 23.11.4 to 23.11.5 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7070
* Added Swahili Locale by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7079
* 5.9.0 POEditor Update - 2024-06-21 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7083

### Inner Beauty
* Base64 encoded image has artifacts by @respencer in https://github.com/ChurchCRM/CRM/pull/7046
* Remove requirement of State field by @respencer in https://github.com/ChurchCRM/CRM/pull/7033
* Better image directories tests by @respencer in https://github.com/ChurchCRM/CRM/pull/7060
* Not all countries have Zip codes or equivalent by @respencer in https://github.com/ChurchCRM/CRM/pull/7064
* Remove IE related code by @respencer in https://github.com/ChurchCRM/CRM/pull/7043
* prevent deprecation warning in InputUtils by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7054
* Propel developer experience improvement by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7051
* PersonEditor cleanup pt1 by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7053
* Fixed typos in codebase reported by `codespell` by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7062
* Drop unused canvass feature by @respencer in https://github.com/ChurchCRM/CRM/pull/7067
* Fix various issues on shared hosting by @respencer in https://github.com/ChurchCRM/CRM/pull/7059
* Started 5.9.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7077
* General whitespace cleanup by @respencer in https://github.com/ChurchCRM/CRM/pull/7074
* Fix sqli vuln by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7086
* Replace rejected character with acceptable one by @respencer in https://github.com/ChurchCRM/CRM/pull/7047
* Fix assorted security issues by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7055
* Fix ubuntu.sh by @respencer in https://github.com/ChurchCRM/CRM/pull/7020
* Stop allowing HTML in Event Sermon text by @respencer in https://github.com/ChurchCRM/CRM/pull/7068

### 👒 Dependencies
* [Snyk] Upgrade chart.js from 4.4.2 to 4.4.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7069
* [Snyk] Upgrade flag-icons from 7.2.1 to 7.2.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7073
* [Snyk] Upgrade fullcalendar from 6.1.11 to 6.1.14 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7072


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.8.0...5.9.3

## [5.8.0](https://github.com/ChurchCRM/CRM/releases/tag/5.8.0) - 2024-05-18

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed

### 🎉 Exciting New Features
* Harmonise icons by @respencer in https://github.com/ChurchCRM/CRM/pull/6946
* Add support for default Zip Code by @respencer in https://github.com/ChurchCRM/CRM/pull/6956
* Cleanup: Event UI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6966
* Inactive people by class by @respencer in https://github.com/ChurchCRM/CRM/pull/7009

### 🪲 Bugs

* Edit for consistant menu rendering by @respencer in https://github.com/ChurchCRM/CRM/pull/6962
* Fix mismatched date formatting by @respencer in https://github.com/ChurchCRM/CRM/pull/7002
* Fix date string formatting to match the rest by @respencer in https://github.com/ChurchCRM/CRM/pull/7008
* Add missing if for Events stat box on dashboard by @respencer in https://github.com/ChurchCRM/CRM/pull/6948
* Update Kiosk  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6937
* Fix HTML for menu count badges by @respencer in https://github.com/ChurchCRM/CRM/pull/6954
* handle situation where shell_exec does not exist by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6952
* Fix "More info" button on People Dashboard by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/6958
* No fatal error when listing null events by @respencer in https://github.com/ChurchCRM/CRM/pull/6964
* Fix Directory Report page size selector by @respencer in https://github.com/ChurchCRM/CRM/pull/6983
* Add missing listOption use statement by @respencer in https://github.com/ChurchCRM/CRM/pull/6986
* Bug: Birthday Calendar works for only current year. #6991 by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/6995
* Fixed bug with Invalid Deposit ID lookup.  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6965
* Bug: Age is not correct in Birthday Calendar. #6990 by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/6997
* fix `location` redirect query parameter on login page by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7005
### 💬 Localization
* 5.7.0 POEditor Update - 2024-04-26 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6963
* 5.8.0 POEditor Update - 2024-05-05 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6996
* 5.8.0 POEditor Update - 2024-05-07 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7000
* 5.8.0 POEditor Update - 2024-05-17 by @github-actions in https://github.com/ChurchCRM/CRM/pull/7031
### Inner Beauty
* Started 5.8.0  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6961
* Delete unreferenced code by @respencer in https://github.com/ChurchCRM/CRM/pull/6957
* Redirect utils cleanup by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6969
* Mustache to twig by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6968
* fix types for SystemCalendar classes by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6979
* Fix issues uncovered from original `mustache-to-twig` branch by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6978
* Delete unused use statement for MenuConfigQuery by @respencer in https://github.com/ChurchCRM/CRM/pull/6985
* run prettier on javascript code by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7006
* run prettier on cypress js files by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7012
* fix php deprecation notice in Countries by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7015
* update all github actions to latest tagged version to resolve deprecations by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7018
* sanitize the CurrentFundraiser provided from query params, use ORM to get fundraiser data by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7014
* sanitize the familyId provided from query params, use ORM to get family data by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7013
* cleanup extract-db-locale-terms.php script by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6960
### 👒 Dependencies
* [Snyk] Upgrade react-datepicker from 6.2.0 to 6.6.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6940
* [Snyk] Upgrade i18next from 23.10.0 to 23.10.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6941
* [Snyk] Upgrade flag-icons from 7.2.0 to 7.2.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6943
* [Snyk] Upgrade i18next from 23.10.1 to 23.11.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6973
* [Snyk] Upgrade i18next from 23.11.0 to 23.11.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6976
* [Snyk] Upgrade react-datepicker from 6.6.0 to 6.7.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6999
* [Snyk] Upgrade i18next from 23.11.1 to 23.11.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6998
* [Snyk] Upgrade react-datepicker from 6.8.0 to 6.9.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7023
* update frontend deps by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7016
### Other Changes

* API: Person/Numbers not used by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6970
* Make middle name searchable by @bigtigerku in https://github.com/ChurchCRM/CRM/pull/6959
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6994
* Cleanup post data in EventAttendance by @respencer in https://github.com/ChurchCRM/CRM/pull/6989
* [Snyk] Upgrade react-datepicker from 6.7.0 to 6.8.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7003
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/7010
* Fix iPDFOutputType setting by @respencer in https://github.com/ChurchCRM/CRM/pull/7027
* add update person tests to ensure functionality by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/7034

## New Contributors
* @bigtigerku made their first contribution in https://github.com/ChurchCRM/CRM/pull/6958

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.7.0...5.8.0

## [5.7.0](https://github.com/ChurchCRM/CRM/releases/tag/5.7.0) - 2024-04-14

<!-- Release notes generated using configuration in .github/release.yml at 351183aac36c9809708bb3d7f960c8c212520350 -->

## What's Changed
### 🎉 Exciting New Features
* System Upgrade UI updates  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6934
* Changed Birth Year range min to 0 from 1901 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6936
### 🪲 Bugs
* Bugfix for CSV Import error by @grayeul in https://github.com/ChurchCRM/CRM/pull/6915
* fix family editor to save wedding date by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6923
* Fix 2FA QR code generation by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6922
* Fix family properties loading issue by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6924
### 💬 Localization
* 5.6.0 POEditor Update - 2024-03-09 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6905
* 5.6.0 POEditor Update - 2024-03-11 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6906
* 5.6.0 POEditor Update - 2024-03-26 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6916
* Locale: India (Hindi & Tamil), Japanese, and China (zh_CN) Flag  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6930
* Social Media names are not localized  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6931
* Bug: User Locale Selection and Flag  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6933
* Locale Cleanup by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6929
* Bug people/verify page has strange header/title by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6935
### Inner Beauty
* update updates to use propel orm by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6867
* better application version check (hopefully remove usage of composer.json in prod) by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6904
* Starting 5.7.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6901
* remove usage of flot and have chartjs be only charting library by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6927
### 👒 Dependencies
* swap sass implementations since node-sass has been deprecated for a while by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6921
* Update composer Libs by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6902
### Other Changes
* Update upgrade process to support churchinfo 1.3.1  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6896
* Add additional method to check for mod_rewrite by @grayeul in https://github.com/ChurchCRM/CRM/pull/6911
* Better custom filtering by @TiagoMRodrigues in https://github.com/ChurchCRM/CRM/pull/6861
* Update CODEOWNERS to use @ChurchCRM/developers group by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6926


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.6.0...5.7.0

## [5.6.0](https://github.com/ChurchCRM/CRM/releases/tag/5.6.0) - 2024-03-08

<!-- Release notes generated using configuration in .github/release.yml at b12fd973d6ed0481fa5e554b9bcc5ecc39f993d9 -->

## What's Changed

### 🪲 Bugs
* 2 bug problem saving from familyeditor by @grayeul in https://github.com/ChurchCRM/CRM/pull/6834
* add more null checks when determining age of person by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6860
* 1 feature request migrate hardcoded states dropdown by @grayeul in https://github.com/ChurchCRM/CRM/pull/6832
* Show group role in GroupView.php by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6865
* Handle adjusting BasePath, utilizing discovered rootPath by @grayeul in https://github.com/ChurchCRM/CRM/pull/6881
* Have GetAge() return -1 if Year is null, and fix return type by @grayeul in https://github.com/ChurchCRM/CRM/pull/6888
### 💬 Localization

* created states for countries in the west indies by @romdricks in https://github.com/ChurchCRM/CRM/pull/6877
* Add South African provinces by @respencer in https://github.com/ChurchCRM/CRM/pull/6883
* Created states for countries by @romdricks in https://github.com/ChurchCRM/CRM/pull/6874
* 5.5.0 POEditor Update - 2024-01-23 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6828
* 5.5.0 POEditor Update - 2024-01-29 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6829
* 5.5.0 POEditor Update - 2024-02-01 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6839
* 5.5.0 POEditor Update - 2024-02-06 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6842
* 5.5.0 POEditor Update - 2024-02-07 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6843
* 5.5.0 POEditor Update - 2024-02-08 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6846
* 5.5.0 POEditor Update - 2024-02-14 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6863
* 5.5.0 POEditor Update - 2024-02-15 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6866
* 5.5.0 POEditor Update - 2024-02-16 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6871
* 5.5.0 POEditor Update - 2024-02-19 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6878
* 5.5.0 POEditor Update - 2024-02-20 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6882
* 5.5.0 POEditor Update - 2024-02-22 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6886
* 5.5.0 POEditor Update - 2024-03-05 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6897

### Inner Beauty
* run alter commands only if able to run alter commands, ignore inserts if already inserted by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6831
* Remove Menu.php by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6808
* update all deps by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6864
* adding xdebug to the test docker just run url with ?XDEBUG_SESSION_START=1 and wait on port 9003 by @TiagoMRodrigues in https://github.com/ChurchCRM/CRM/pull/6857
* Build/5.6.0 - Build / Version  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6900
### 👒 Dependencies
* Bump ckeditor4 from 4.23.0 to 4.24.0 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6844
* Bump ip from 2.0.0 to 2.0.1 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6885
* Bump es5-ext from 0.10.61 to 0.10.63 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6892
* Upgrade deps by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6898
### Other Changes
* [Snyk] Upgrade i18next from 23.7.16 to 23.7.18 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6859
* [Snyk] Upgrade react-bootstrap from 2.9.2 to 2.10.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6845
* Fix typos: thubm -> thumb(nail) by @grayeul in https://github.com/ChurchCRM/CRM/pull/6890
* Add clearing of Lat/Long to verify null values are functional by @grayeul in https://github.com/ChurchCRM/CRM/pull/6894

## New Contributors
* @grayeul made their first contribution in https://github.com/ChurchCRM/CRM/pull/6834
* @TiagoMRodrigues made their first contribution in https://github.com/ChurchCRM/CRM/pull/6857
* @romdricks made their first contribution in https://github.com/ChurchCRM/CRM/pull/6874
* @respencer made their first contribution in https://github.com/ChurchCRM/CRM/pull/6883

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.5.0...5.6.0

## [5.5.0](https://github.com/ChurchCRM/CRM/releases/tag/5.5.0) - 2024-01-19

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed
### 🎉 Exciting New Features
* get group view datatable to workable state by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6814
### 🪲 Bugs
* fix error when checking emptiness of $Year by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6806
* $interval should never be false if iRemotePhotoCacheDuration is invalid by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6805
* set up dependency injection container for setup routes by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6821
* [custom field] mb_substr($fieldInfo->name, 1) string must be cast to int to do arithmetic by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6825
### 💬 Localization
* 5.5.0 POEditor Update - 2024-01-10 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6811
* 5.5.0 POEditor Update - 2024-01-11 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6813
* 5.5.0 POEditor Update - 2024-01-12 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6815
* 5.5.0 POEditor Update - 2024-01-13 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6817
* 5.5.0 POEditor Update - 2024-01-15 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6818
* 5.5.0 POEditor Update - 2024-01-17 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6820
* 5.5.0 POEditor Update - 2024-01-18 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6823
### Inner Beauty
* convert tedious sql strings to safer orm operations by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6779
* update phpstan and rector, run rector after updating by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6809
* improve bug report template by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6816
### 👒 Dependencies
* update as many js deps as possible by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6801
* update grunt-poeditor-gd grunt i18next by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6804
* update as many dependencies as possible by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6822


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.4.3...5.5.0

## [5.4.3](https://github.com/ChurchCRM/CRM/releases/tag/5.4.3) - 2024-01-04

<!-- Release notes generated using configuration in .github/release.yml at 5faef0b90529a9986331938ac2c6aa8d45e67a21 -->

## What's Changed
### 🪲 Bugs
* Fixed render of photos by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6799
* fix system updater to prevent TypeError by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6800
### 👒 Dependencies
* [Snyk] Upgrade i18next from 23.7.8 to 23.7.9 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6802


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.4.2...5.4.3

## [5.4.2](https://github.com/ChurchCRM/CRM/releases/tag/5.4.2) - 2024-01-01

<!-- Release notes generated using configuration in .github/release.yml at ee95ff7e811893cc0282f1a8668469c9e91e41af -->

## What's Changed
### 🪲 Bugs
* Bug: Fix backup download of the files by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6794
* fix javascript calls to delete routes which currently don't work by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6796


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.4.1...5.4.2

## [5.4.1](https://github.com/ChurchCRM/CRM/releases/tag/5.4.1) - 2023-12-30

<!-- Release notes generated using configuration in .github/release.yml at e8cf7233b48002dc9a3285c162367644aaa0532f -->

## What's Changed
### 🪲 Bugs
* fix error on QuerySQL being thrown on webui by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6783
* fix deposit slip generation by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6787
* 5.4.1 Bug fixes  by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6788
* fix some issues in the finance/deposit routes by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6786

### 💬 Localization
* 5.4.0 POEditor Update - 2023-12-28 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6782
* 5.4.0 POEditor Update - 2023-12-29 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6789
### Inner Beauty
* Small Code Cleanup  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6791
### Other Changes
* [Snyk] Upgrade i18next from 23.7.7 to 23.7.8 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6785


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.4.0...5.4.1

## [5.4.0](https://github.com/ChurchCRM/CRM/releases/tag/5.4.0) - 2023-12-27

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed

### 🪲 Bugs
* fix issue with group list datatable by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6729
* finding and fixing in the slim upgrade branch by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6730
* fix issue with delete api routes by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6731
* fix calendar and other scenarios with similar errors by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6732
* fix issues in finance payment routes by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6739
* fix select2 on PledgeEditor by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6753
* Fix dashboard birthdays and address various minor code smells by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6762
* allow minimal person data to be entered by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6769
* fix various errors when interacting with 'cart' by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6767
* [slim-upgrade] fix kiosk url so works as well as it did before by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6774
### 💬 Localization
* 5.4.0 POEditor Update - 2023-12-12 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6745
* 5.4.0 POEditor Update - 2023-12-15 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6749
* 5.4.0 POEditor Update - 2023-12-23 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6768
* 5.4.0 POEditor Update - 2023-12-24 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6770
* 5.4.0 POEditor Update - 2023-12-26 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6771
### Inner Beauty
* Starting 5.4.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6740
* fix typo in POEditor commit message by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6750
* update all www.churchcrm.io to churchcrm.io by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6763
* add more minor code smell by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6764
* add more types to php code by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6765
* throw http exceptions when possible, try to pass around arrays instead of stringified arrays by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6752

### 👒 Dependencies
* [Snyk] Upgrade flag-icons from 6.13.2 to 6.14.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6727
* [Snyk] Upgrade i18next from 23.6.0 to 23.7.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6733
* [Snyk] Upgrade flag-icons from 6.14.0 to 6.15.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6734
* [Snyk] Upgrade i18next from 23.7.1 to 23.7.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6735
* Slim upgrade to v4 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6606
* [Snyk] Upgrade i18next from 23.7.3 to 23.7.6 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6741
* [Snyk] Upgrade react-datepicker from 4.21.0 to 4.23.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6748
* [Snyk] Upgrade i18next from 23.7.6 to 23.7.7 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6755
* [Snyk] Upgrade fullcalendar from 6.1.9 to 6.1.10 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6759
* [Snyk] Upgrade @fortawesome/fontawesome-free from 6.4.2 to 6.5.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6758
* [Snyk] Upgrade react-datepicker from 4.23.0 to 4.24.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6760
* [Snyk] Upgrade @fortawesome/fontawesome-free from 6.5.0 to 6.5.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6761
* [Snyk] Upgrade chart.js from 4.4.0 to 4.4.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6775
### Other Changes
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6543
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6696
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6708
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6717
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6723
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6725
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6736
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6737
* Apply fixes from StyleCI by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6754
* make sure dashed lines aren't selectable by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6766
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6772
* add ids to anchors, write some test for profile page by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6778
* rename cypress specs with typos by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6780


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.3.1...5.4.0

## [5.3.1](https://github.com/ChurchCRM/CRM/releases/tag/5.3.1) - 2023-11-26

<!-- Release notes generated using configuration in .github/release.yml at master -->

## What's Changed
### 🪲 Bugs
* fix tax report incorrect args in `ChurchCRM\Reports\PdfTaxReport::finishPage()` by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6718
* fix diacritics for directory report by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6719
* add missing fiscal years for pledge comparison by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6720
### 💬 Localization
* 5.3.1 POEditor Update - 2023-11-22 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6702
### Inner Beauty
* Started 5.3.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6699
* Add php types to auth classes by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6703
* align styleci and phpcs to same psr12 coding standard by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6705
* remove use of extract in CSVImport.php by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6710
### Other Changes
* reformat README with markdown best practice, add repo stats by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6701
* Apply fixes from StyleCI by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6707


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.3.0...5.3.1

## [5.3.0](https://github.com/ChurchCRM/CRM/releases/tag/5.3.0) - 2023-11-20

<!-- Release notes generated using configuration in .github/release.yml at 3ae48af8b207ae68423e0086d50797bee99b5f51 -->

## What's Changed
### 🛠 Breaking Changes
* No Longer Support PHP 8.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6683

### Other Changes
* Upgrade System Updates by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6685

### 🪲 Bugs
* Added missing Fiscal Year to Query View by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6626
### Inner Beauty
* PHP Import cleanup vis phpStorm by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6692
* moved from docker-compose  to docker compose by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6687


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.2.3...5.3.0

## [5.2.3](https://github.com/ChurchCRM/CRM/releases/tag/5.2.3) - 2023-11-18

<!-- Release notes generated using configuration in .github/release.yml at be4959b8ac452fdd28e77683f5c622ea3ee3cda9 -->

## What's Changed

### 🪲 Bugs
* fix error in /api/families/updated by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6668
* Fix sunday school page bug by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6675
* Fix sunday school graphs by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6680
### 💬 Localization
* 5.2.3 POEditor Update - 2023-11-17 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6678
### Inner Beauty
* Starting 5.2.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6676
* make build faster by running things in parallel, switch release to node script for consistency by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6679
### Other Changes
* Apply fixes from StyleCI by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6674
* Update README.md by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6677
* tell user what version of php they're running for quality of life by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6682


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.2.2...5.2.3

## [5.2.2](https://github.com/ChurchCRM/CRM/releases/tag/5.2.2) - 2023-11-14

<!-- Release notes generated using configuration in .github/release.yml at 448bf6319317c250a0cef249583b6bd032fe3823 -->

## What's Changed
### 🪲 Bugs
* UI Bugs: PDF Labels & Group to Cart by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6661
* fix error when updating Family by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6654
* allow for empty birthday when initially inputting person by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6652
* fix adding group roles by properly retrieving required service from the container by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6665
### 💬 Localization
* 5.2.2 POEditor Update - 2023-11-13 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6662
### Inner Beauty
* Build/5.2.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6660
* Js minor cleanup by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6666
### Other Changes
* add debug step request to bug issue, comment out user prompts by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6667


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.2.1...5.2.2

## [5.2.1](https://github.com/ChurchCRM/CRM/releases/tag/5.2.1) - 2023-11-12

<!-- Release notes generated using configuration in .github/release.yml at 423fe651ee875fd4a78777230bec39fca912d6b8 -->

## What's Changed
### 🪲 Bugs
* UI: Bug Fix - Left Nav Bar  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6650
### Inner Beauty
* re-namespace propel classes to conform to psr-4 by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6642
* StyleCI now uses .styleci.yml by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6648
### Other Changes
* Apply fixes from StyleCI by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6641
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6646


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.2.0...5.2.1

## [5.2.0](https://github.com/ChurchCRM/CRM/releases/tag/5.2.0) - 2023-11-11

<!-- Release notes generated using configuration in .github/release.yml at 13bd89991654dba2f4142a8c2cd4c1fa5ab7fa80 -->

## What's Changed
### 🪲 Bugs
* additional fixes and code smell cleanup by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6636
### 💬 Localization
* Locale: 5.2.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6645
### Inner Beauty
* Update README badges to always reflect latest release by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6633
### 👒 Dependencies
* Upgrade frontend deps by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6630
### Other Changes
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6632


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.1.1...5.2.0

## [5.1.1](https://github.com/ChurchCRM/CRM/releases/tag/5.1.1) - 2023-11-07

<!-- Release notes generated using configuration in .github/release.yml at 39fafec969a421c28502f3484317c38695373786 -->

## What's Changed
### 🪲 Bugs
* Bug Fixes & Cleanup 5.1.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6625
* attempt to fix potentially-undefined fn apache_get_modules, only allow upgrade when logged in, fix kiosk routes, misc minor cleanup by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6627
### 💬 Localization
* 5.1.1 POEditor Update - 2023-11-06 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6623
### Inner Beauty
* 5.1.1 - Build Script Cleanup by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6620
* start removal of extract function from codebase + fix report tests by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6613
* Development Updates: GitHub Templates & Scripts  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6624
* Locale: tool updates & 5.1.1 download by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6622


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.1.0...5.1.1

## [5.1.0](https://github.com/ChurchCRM/CRM/releases/tag/5.1.0) - 2023-11-05

<!-- Release notes generated using configuration in .github/release.yml at 37f44cdcdb2b2a1a0b48b2b217f19990078c86e6 -->

## What's Changed
### 🪲 Bugs
* handle situation where iRemotePhotoCacheDuration is not set, minor cleanup around code by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6607
* Fixed Issue Reporting by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6616
* Bug: Fix Event Checkin by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6617
### 💬 Localization
* 5.1.0 POEditor Update - en_GB & ro_RO by @github-actions in https://github.com/ChurchCRM/CRM/pull/6603
* 5.1.0 POEditor Update - 2023-11-02 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6608
### Inner Beauty
* introduce phpcs and conform to psr12 by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6600
* Build: 5.1.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6604
* Remove System regestration feature by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6605
* Moved from Custom github action for Release Notes to github builtin  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6609
* better utilize docker layer cache, make `up` rebuild by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6612
### 👒 Dependencies
* [Snyk] Upgrade ckeditor4 from 4.22.1 to 4.23.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6611
### Other Changes
* enable ORM logs only if we are in debug mode by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6618


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.0.5...5.1.0

## [5.0.5](https://github.com/ChurchCRM/CRM/releases/tag/5.0.5) - 2023-10-31

## :star: Enhancements

- Ckeditor [#6601](https://github.com/ChurchCRM/CRM/pull/6601)
- Event Editor  Default Date and Date Range Issue [#6137](https://github.com/ChurchCRM/CRM/issues/6137)

## :speech_balloon: Localization

- 5.0.5 POEditor Update - ro_RO [#6599](https://github.com/ChurchCRM/CRM/pull/6599)

## :gear: Inner Beauty

- Remove permissions for usAddressVerification [#6598](https://github.com/ChurchCRM/CRM/pull/6598)
- More https corrections [#6594](https://github.com/ChurchCRM/CRM/pull/6594)
- update various npm dependencies [#6588](https://github.com/ChurchCRM/CRM/pull/6588)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@brianteeman](https://github.com/brianteeman)
- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)
- [@DAcodedBEAT](https://github.com/DAcodedBEAT)
 
- ## What's Changed
* remove tooltip by @brianteeman in https://github.com/ChurchCRM/CRM/pull/6596
* More https corrections by @brianteeman in https://github.com/ChurchCRM/CRM/pull/6594
* Using Docker Mail Server  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6597
* update various npm dependencies by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6588
* add logs to upgrade flow to make it easier to triage errors by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6589
* Remove permissions for usAddressVerification by @brianteeman in https://github.com/ChurchCRM/CRM/pull/6598
* Ckeditor by @brianteeman in https://github.com/ChurchCRM/CRM/pull/6601
* 5.0.5 POEditor Update - ro_RO by @github-actions in https://github.com/ChurchCRM/CRM/pull/6599


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.0.4...5.0.5

## [5.0.4](https://github.com/ChurchCRM/CRM/releases/tag/5.0.4) - 2023-10-29

## :speech_balloon: Localization

- 5.0.4 Locale update & Czech locale added [#6583](https://github.com/ChurchCRM/CRM/pull/6583)

## :beetle: Bugs

- Bug fixes - User Setting / View Person / CVS Import [#6581](https://github.com/ChurchCRM/CRM/pull/6581)
- Removed Intelligent Search Technolgy as a tool [#6585](https://github.com/ChurchCRM/CRM/pull/6585)
- intelligentsearch was shutdown  [#6584](https://github.com/ChurchCRM/CRM/issues/6584)

## :gear: Inner Beauty

- spelling dashbaord/dashboard [#6590](https://github.com/ChurchCRM/CRM/pull/6590)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@brianteeman](https://github.com/brianteeman)
- [@DawoudIO](https://github.com/DawoudIO)
- ## What's Changed
* Bug fixes - User Setting / View Person / CVS Import by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6581
* 5.0.4 Locale update & Czech locale added by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6583
* Removed Intelligent Search Technolgy as a tool by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6585
* Test/ensure load top pages by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6586
* 5.0.4 POEditor Update - 2023-10-29 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6587
* spelling dashbaord/dashboard by @brianteeman in https://github.com/ChurchCRM/CRM/pull/6590
* Footer copyright by @brianteeman in https://github.com/ChurchCRM/CRM/pull/6593
* Update Config.php.example by @brianteeman in https://github.com/ChurchCRM/CRM/pull/6592


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.0.3...5.0.4

## [5.0.3](https://github.com/ChurchCRM/CRM/releases/tag/5.0.3) - 2023-10-28

## :beetle: Bugs

- Fixed Person / Family photo & thumbnail API [#6579](https://github.com/ChurchCRM/CRM/pull/6579)
- Setup working on PHP 8 [#6578](https://github.com/ChurchCRM/CRM/pull/6578)

## :gear: Inner Beauty

- more minor code cleanup [#6577](https://github.com/ChurchCRM/CRM/pull/6577)
- Remove php 7 from scripts [#6576](https://github.com/ChurchCRM/CRM/pull/6576)
- Test Build branches - start test docker and test  [#6573](https://github.com/ChurchCRM/CRM/pull/6573)
- PHP Cleanup - DATA / DTO FILES [#6572](https://github.com/ChurchCRM/CRM/pull/6572)
- Updated Slim MVC to use $app vs $this [#6571](https://github.com/ChurchCRM/CRM/pull/6571)
- Bump crypto-js from 4.1.1 to 4.2.0 [#6565](https://github.com/ChurchCRM/CRM/pull/6565)
- Slight improvement to package.json script(s) [#6564](https://github.com/ChurchCRM/CRM/pull/6564)
- [rector] migrate to ruleset LevelSetList::UP_TO_PHP_71 [#6563](https://github.com/ChurchCRM/CRM/pull/6563)
- [rector] migrate to ruleset LevelSetList::UP_TO_PHP_70 [#6559](https://github.com/ChurchCRM/CRM/pull/6559)
- Starting 5.0.3 [#6556](https://github.com/ChurchCRM/CRM/pull/6556)
- find and fix various issues found in the codebase [#6554](https://github.com/ChurchCRM/CRM/pull/6554)
- apply rector level set list rules to bring codebase to php 7.4 [#6550](https://github.com/ChurchCRM/CRM/pull/6550)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)
- [@dependabot[bot]](https://github.com/apps/dependabot)
- [@DAcodedBEAT](https://github.com/DAcodedBEAT)

## What's Changed
* Starting 5.0.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6556
* [rector] migrate to ruleset LevelSetList::UP_TO_PHP_70 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6559
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6558
* [rector] migrate to ruleset LevelSetList::UP_TO_PHP_71 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6563
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6562
* [rector] migrate to ruleset LevelSetList::UP_TO_PHP_73 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6567
* Apply fixes from StyleCI by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6566
* Bump crypto-js from 4.1.1 to 4.2.0 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6565
* apply rector level set list rules to bring codebase to php 7.4 by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6550
* Slight improvement to package.json script(s) by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6564
* Updated Slim MVC to use $app vs $this by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6571
* PHP Cleanup - DATA / DTO FILES by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6572
* Dev class cleanup 5.0.x by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6574
* 5.0.3 POEditor Update - 2023-10-27 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6575
* find and fix various issues found in the codebase by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6554
* Setup working on PHP 8 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6578
* more minor code cleanup by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6577
* Test Build branches - start test docker and test  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6573
* Fixed Person / Family photo & thumbnail API by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6579
* Integration Test Reorder by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6580
* Remove php 7 from scripts by @DAcodedBEAT in https://github.com/ChurchCRM/CRM/pull/6576


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/5.0.2...5.0.3

## [5.0.2](https://github.com/ChurchCRM/CRM/releases/tag/5.0.2) - 2023-10-25

## :star: Enhancements
- Support for PHP 8  [#6482](https://github.com/ChurchCRM/CRM/pull/6482)

## :beetle: Bugs

- Fix Event buttons [#6540](https://github.com/ChurchCRM/CRM/pull/6540)
- After editing a person that person shows up twice in Family members in Person Profile. [#6478](https://github.com/ChurchCRM/CRM/issues/6478)
- CAN'T EDIT OR DELETE EVENTS FROM Listing All Church Events [#6476](https://github.com/ChurchCRM/CRM/issues/6476)
- Spelling errors [#6493](https://github.com/ChurchCRM/CRM/pull/6493)
- DepositSlipEditor spelling [#6538](https://github.com/ChurchCRM/CRM/pull/6538)
- add dockerfile to python3 and composer fix [#6495](https://github.com/ChurchCRM/CRM/pull/6495)

## :gear: Inner Beauty
* [Snyk] Upgrade flag-icons from 6.11.0 to 6.11.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6553
- [Snyk] Upgrade react-bootstrap from 2.8.0 to 2.9.0 [#65[33](https://github.com/ChurchCRM/CRM/actions/runs/6600001186/job/17929588010#step:4:34)](https://github.com/ChurchCRM/CRM/pull/6533)
- [Snyk] Upgrade jquery from 3.7.0 to 3.7.1 [#6526](https://github.com/ChurchCRM/CRM/pull/6526)
- [Snyk] Upgrade @fortawesome/fontawesome-free from 6.4.0 to 6.4.2 [#6523](https://github.com/ChurchCRM/CRM/pull/6523)
- [Snyk] Upgrade bootstrap-datepicker from 1.9.0 to 1.10.0 [#6496](https://github.com/ChurchCRM/CRM/pull/6496)
- [Snyk] Upgrade inputmask from 5.0.7 to 5.0.8 [#6468](https://github.com/ChurchCRM/CRM/pull/6468)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@brianteeman](https://github.com/brianteeman)
- [@DAcodedBEAT](https://github.com/DAcodedBEAT)
- [@kboghdady](https://github.com/kboghdady)
- [@DawoudIO](https://github.com/DawoudIO)
- [@uemura[45](https://github.com/ChurchCRM/CRM/actions/runs/6600001186/job/17929588010#step:4:46)01](https://github.com/uemura4[50](https://github.com/ChurchCRM/CRM/actions/runs/6600001186/job/17929588010#step:4:51)1)
- [@github-actions[bot]](https://github.com/apps/github-actions)





**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/4.5.4...5.0.2

## [5.0.0](https://github.com/ChurchCRM/CRM/releases/tag/5.0.0) - 2023-10-21

## :star: Enhancements
- Support for PHP 8  [#6482](https://github.com/ChurchCRM/CRM/pull/6482)

## :beetle: Bugs

- Fix Event buttons [#6540](https://github.com/ChurchCRM/CRM/pull/6540)
- After editing a person that person shows up twice in Family members in Person Profile. [#6478](https://github.com/ChurchCRM/CRM/issues/6478)
- CAN'T EDIT OR DELETE EVENTS FROM Listing All Church Events [#6476](https://github.com/ChurchCRM/CRM/issues/6476)
- Spelling errors [#6493](https://github.com/ChurchCRM/CRM/pull/6493)
- DepositSlipEditor spelling [#6538](https://github.com/ChurchCRM/CRM/pull/6538)
- add dockerfile to python3 and composer fix [#6495](https://github.com/ChurchCRM/CRM/pull/6495)

## :gear: Inner Beauty
- [Snyk] Upgrade flag-icons from 6.6.6 to 6.7.0 [#6507](https://github.com/ChurchCRM/CRM/pull/6507)
- [Snyk] Upgrade flag-icons from 6.8.0 to 6.9.5 [#6525](https://github.com/ChurchCRM/CRM/pull/6525)
- [Snyk] Upgrade flag-icons from 6.9.5 to 6.11.0 [#6529](https://github.com/ChurchCRM/CRM/pull/6529)

- [Snyk] Upgrade react-bootstrap from 2.7.1 to 2.7.2 [#6460](https://github.com/ChurchCRM/CRM/pull/6460) 
- [Snyk] Upgrade react-bootstrap from 2.7.2 to 2.7.3 [#6480](https://github.com/ChurchCRM/CRM/pull/6480)
- [Snyk] Upgrade react-bootstrap from 2.7.3 to 2.7.4 [#6485](https://github.com/ChurchCRM/CRM/pull/6485)
- [Snyk] Upgrade react-bootstrap from 2.7.4 to 2.8.0 [#6514](https://github.com/ChurchCRM/CRM/pull/6514)
- [Snyk] Upgrade react-bootstrap from 2.8.0 to 2.9.0 [#65[33](https://github.com/ChurchCRM/CRM/actions/runs/6600001186/job/17929588010#step:4:34)](https://github.com/ChurchCRM/CRM/pull/6533)
- [Snyk] Upgrade jquery from 3.6.4 to 3.7.0 [#6492](https://github.com/ChurchCRM/CRM/pull/6492)
- [Snyk] Upgrade jquery from 3.7.0 to 3.7.1 [#6526](https://github.com/ChurchCRM/CRM/pull/6526)
- [Snyk] Upgrade @fortawesome/fontawesome-free from 6.3.0 to 6.4.0 [#6472](https://github.com/ChurchCRM/CRM/pull/6472)
- [Snyk] Upgrade @fortawesome/fontawesome-free from 6.4.0 to 6.4.2 [#6523](https://github.com/ChurchCRM/CRM/pull/6523)
- [Snyk] Upgrade bootstrap-datepicker from 1.9.0 to 1.10.0 [#6496](https://github.com/ChurchCRM/CRM/pull/6496)
- [Snyk] Upgrade inputmask from 5.0.7 to 5.0.8 [#6468](https://github.com/ChurchCRM/CRM/pull/6468)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@brianteeman](https://github.com/brianteeman)
- [@DAcodedBEAT](https://github.com/DAcodedBEAT)
- [@kboghdady](https://github.com/kboghdady)
- [@DawoudIO](https://github.com/DawoudIO)
- [@uemura[45](https://github.com/ChurchCRM/CRM/actions/runs/6600001186/job/17929588010#step:4:46)01](https://github.com/uemura4[50](https://github.com/ChurchCRM/CRM/actions/runs/6600001186/job/17929588010#step:4:51)1)
- [@github-actions[bot]](https://github.com/apps/github-actions)

## [4.5.4](https://github.com/ChurchCRM/CRM/releases/tag/4.5.4) - 2023-03-06

## What's Changed
* Starting 4.5.4 release  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6454
* 4.5.4 POEditor Update - 2023-03-02 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6456

* [Snyk] Upgrade @fortawesome/fontawesome-free from 6.2.1 to 6.3.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6452
* [Snyk] Upgrade jquery from 3.6.1 to 3.6.2 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6136
* [Snyk] Upgrade jquery from 3.6.2 to 3.6.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6457
* [Snyk] Upgrade react-bootstrap from 2.7.0 to 2.7.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6458

* Bump http-cache-semantics from 4.1.0 to 4.1.1 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6447
* Bump jszip from 3.7.1 to 3.10.1 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6445

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/4.5.3...4.5.4

## [4.5.3](https://github.com/ChurchCRM/CRM/releases/tag/4.5.3) - 2023-01-02

## :beetle: Bugs

- fixed #6129 with bad user setting [#61[32](https://github.com/ChurchCRM/CRM/actions/runs/3819890766/jobs/6497804167#step:4:33)](https://github.com/ChurchCRM/CRM/pull/6132)
- Bug in fundraising [#6129](https://github.com/ChurchCRM/CRM/issues/6129)
- fixed sRootPath var in DB backup / restore
- fixed renamed getPeople -> getPeopleSorted in Family / Person Pages
- Fixed Event bad array declair
- Fixed demo db to have the correct data for cypress testing.

## :gear: Inner Beauty

- [Snyk] Upgrade react-bootstrap from 2.6.0 to 2.7.0 [#6128](https://github.com/ChurchCRM/CRM/pull/6128)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@DawoudIO](https://github.com/DawoudIO)


## What's Changed
* Starting 4.5.3 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6131
* [Snyk] Upgrade react-bootstrap from 2.6.0 to 2.7.0 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6128
* fixed #6129 with bad user setting by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6132


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/4.5.2...4.5.3

## [4.5.2](https://github.com/ChurchCRM/CRM/releases/tag/4.5.2) - 2022-12-29

## What's Changed
* Bug: Fixed method call for family getSalutation  by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6125
* Release 4.5.2 started by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6124
* Bump qs from 6.5.2 to 6.5.3 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6121
* 4.5.1 POEditor Update - 2022-12-29 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6118
* [Snyk] Upgrade @fortawesome/fontawesome-free from 6.2.0 to 6.2.1 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6120


**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/4.5.1...4.5.2

## [4.5.1](https://github.com/ChurchCRM/CRM/releases/tag/4.5.1) - 2022-12-04


## :beetle: Bugs

- fixed API call to delete user [#6116](https://github.com/ChurchCRM/CRM/pull/6116)
- Bug: replaced bg-aqua to bg-gray [#6115](https://github.com/ChurchCRM/CRM/pull/6115)
- ChurchCRM 4.5.0 throws Fatal error while running setup [#6072](https://github.com/ChurchCRM/CRM/issues/6072)

## :gear: Inner Beauty

- Bump major lib and react to newer versions
- Bump mime, grunt-curl and grunt-http [#6112](https://github.com/ChurchCRM/CRM/pull/6112)
- Bump qs and grunt-http [#6108](https://github.com/ChurchCRM/CRM/pull/6108)
- [Snyk] Upgrade flag-icons from 6.6.0 to 6.6.6 [#6097](https://github.com/ChurchCRM/CRM/pull/6097)
- [Snyk] Upgrade i18n from 0.15.0 to 0.15.1 [#6096](https://github.com/ChurchCRM/CRM/pull/6096)
- [Snyk] Upgrade @fortawesome/fontawesome-free from 6.1.2 to 6.2.0 [#6080](https://github.com/ChurchCRM/CRM/pull/6080)
- [Snyk] Upgrade jquery from 3.6.0 to 3.6.1 [#6079](https://github.com/ChurchCRM/CRM/pull/6079)
- [Snyk] Security upgrade node-sass from 7.0.1 to 7.0.2 [#6073](https://github.com/ChurchCRM/CRM/pull/6073)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@DawoudIO](https://github.com/DawoudIO)
- [@chiebert](https://github.com/chiebert)
- [@dependabot[bot]](https://github.com/apps/dependabot)

## What's Changed
* [Snyk] Security upgrade node-sass from 7.0.1 to 7.0.2 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6073
* Bump moment from 2.29.3 to 2.29.4 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6076
* Starting 4.5.1 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6077
* 4.5.1 POEditor Update - 2022-09-28 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6085
* [Snyk] Upgrade @fortawesome/fontawesome-free from 6.1.2 to 6.2.0 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6080
* [Snyk] Upgrade jquery from 3.6.0 to 3.6.1 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6079
* [Snyk] Upgrade node-sass from 7.0.2 to 7.0.3 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6087
* 4.5.1 POEditor Update - 2022-09-30 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6086
* Bump moment-timezone from 0.5.33 to 0.5.37 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6071
* 4.5.1 POEditor Update - 2022-10-01 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6088
* 4.5.1 POEditor Update - 2022-10-04 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6089
* [Snyk] Upgrade i18n from 0.15.0 to 0.15.1 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6096
* 4.5.1 POEditor Update - 2022-10-14 by @github-actions in https://github.com/ChurchCRM/CRM/pull/6095
* [Snyk] Upgrade flag-icons from 6.5.1 to 6.6.0 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6056
* Bump minimatch from 3.0.4 to 3.0.8 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6103
* Bump loader-utils from 1.4.0 to 1.4.2 by @dependabot in https://github.com/ChurchCRM/CRM/pull/6102
* [Snyk] Upgrade flag-icons from 6.6.0 to 6.6.6 by @snyk-bot in https://github.com/ChurchCRM/CRM/pull/6097
* Add $delimiter instead of hardcoded commas by @chiebert in https://github.com/ChurchCRM/CRM/pull/6093
* Bump qs and grunt-http by @dependabot in https://github.com/ChurchCRM/CRM/pull/6108
* Bump mime, grunt-curl and grunt-http by @dependabot in https://github.com/ChurchCRM/CRM/pull/6112
* Better family roll up csv export 6092 by @chiebert in https://github.com/ChurchCRM/CRM/pull/6094
* Bug: replaced bg-aqua to bg-gray by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6115
* fixed API call to delete user by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6116
* Fixed  new system setup error in 4.5.0 by @DawoudIO in https://github.com/ChurchCRM/CRM/pull/6117

## New Contributors
* @chiebert made their first contribution in https://github.com/ChurchCRM/CRM/pull/6093

**Full Changelog**: https://github.com/ChurchCRM/CRM/compare/4.5.0...4.5.1

## [4.5.0](https://github.com/ChurchCRM/CRM/releases/tag/4.5.0) - 2022-08-30

This is a Major release, we have upgraded the software to ensure we patch the security issues, and this required major upgrades to the UI. If you see issues please let us know. 

## :star: Enhancements

- System Settings:  Family Role is now a dropdown [#6039](https://github.com/ChurchCRM/CRM/pull/6039)
- Add family role with changed order - impact on system setting member tab??? [#1176](https://github.com/ChurchCRM/CRM/issues/1176)
- 
## :speech_balloon: Localization

- add Ukrainian locale  [#6022](https://github.com/ChurchCRM/CRM/issues/6022)
- Add Greek Locale [#6021](https://github.com/ChurchCRM/CRM/issues/6021)
- Add Amharic - Ethiopia locale  [#6020](https://github.com/ChurchCRM/CRM/issues/6020)

## :beetle: Bugs

- Help & Manual Link Does Not Work [#5995](https://github.com/ChurchCRM/CRM/issues/5995)
- Update NewsLetterLabels.php [#5984](https://github.com/ChurchCRM/CRM/pull/5984)
- Newsletter labels printing country [#5983](https://github.com/ChurchCRM/CRM/issues/5983)

## :gear: Inner Beauty

- Update AdminLTE to 3.x [#5[34](https://github.com/ChurchCRM/CRM/runs/8097524846?check_suite_focus=true#step:4:35)8](https://github.com/ChurchCRM/CRM/issues/5348)
- FA Icon v6 updates [#6045](https://github.com/ChurchCRM/CRM/pull/6045)
- 2022 june skin updates [#60[33](https://github.com/ChurchCRM/CRM/runs/8097524846?check_suite_focus=true#step:4:34)](https://github.com/ChurchCRM/CRM/pull/6033)
- [Snyk] Upgrade @fortawesome/fontawesome-free from 6.1.1 to 6.1.2 [#6065](https://github.com/ChurchCRM/CRM/pull/6065)
- [Snyk] Upgrade bootstrap from 4.6.1 to 4.6.2 [#6064](https://github.com/ChurchCRM/CRM/pull/6064)
- [Snyk] Security upgrade jquery-validation from 1.19.4 to 1.19.5 [#6054](https://github.com/ChurchCRM/CRM/pull/6054)
- [Snyk] Upgrade bootstrap from 4.6.0 to 4.6.1 [#6038](https://github.com/ChurchCRM/CRM/pull/6038)
- [Snyk] Upgrade fullcalendar from 3.10.2 to 3.10.5 [#6037](https://github.com/ChurchCRM/CRM/pull/6037)
- [Snyk] Upgrade i18n from 0.13.3 to 0.15.0 [#60[36](https://github.com/ChurchCRM/CRM/runs/8097524846?check_suite_focus=true#step:4:37)](https://github.com/ChurchCRM/CRM/pull/6036)
- Bump guzzlehttp/guzzle from 7.3.0 to 7.4.4 in /src [#6015](https://github.com/ChurchCRM/CRM/pull/6015)
- Bump jquery-validation from 1.19.3 to 1.19.4 [#6014](https://github.com/ChurchCRM/CRM/pull/6014)
- Bump grunt from 1.5.2 to 1.5.3 [#6013](https://github.com/ChurchCRM/CRM/pull/6013)
- Bump simple-get from 3.1.0 to 3.1.1 [#6004](https://github.com/ChurchCRM/CRM/pull/6004)
- Bump grunt from 1.3.0 to 1.5.2 [#6000](https://github.com/ChurchCRM/CRM/pull/6000)
- [Snyk] Security upgrade node-sass from 6.0.1 to 7.0.0 [#[59](https://github.com/ChurchCRM/CRM/runs/8097524846?check_suite_focus=true#step:4:60)75](https://github.com/ChurchCRM/CRM/pull/5975)
- Bump lcobucci/jwt from 3.4.5 to 3.4.6 in /src [#59[64](https://github.com/ChurchCRM/CRM/runs/8097524846?check_suite_focus=true#step:4:65)](https://github.com/ChurchCRM/CRM/pull/5964)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@wuletawwonte](https://github.com/wuletawwonte)
- [@javacorey](https://github.com/javacorey)
- [@MrClever](https://github.com/MrClever)
- [@jibaomansaray](https://github.com/jibaomansaray)
- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)
- [@dependabot[bot]](https://github.com/apps/dependabot)

## [4.4.5](https://github.com/ChurchCRM/CRM/releases/tag/4.4.5) - 2021-08-05

## :speech_balloon: Localization

- 4.4.5 POEditor Update - Dutch at 100% translated [#5801](https://github.com/ChurchCRM/CRM/pull/5801)

## :gear: Inner Beauty

- Bump tar from 6.1.0 to 6.1.2 [#5802](https://github.com/ChurchCRM/CRM/pull/5802)
- Starting 4.4.5 [#5793](https://github.com/ChurchCRM/CRM/pull/5793)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)
- [@dependabot[bot]](https://github.com/apps/dependabot)

## [4.4.4](https://github.com/ChurchCRM/CRM/releases/tag/4.4.4) - 2021-07-09

This is a security and localization release.

## :speech_balloon: Localization

it_IT is now 100% localized 
- 4.4.4 POEditor Update - Locale Cleanup [#5790](https://github.com/ChurchCRM/CRM/pull/5790)
- 4.4.4 POEditor Update - it_IT [#5781](https://github.com/ChurchCRM/CRM/pull/5781)
- 4.4.4 POEditor Update - it_IT [#5779](https://github.com/ChurchCRM/CRM/pull/5779)

## :gear: Inner Beauty

- [Snyk] Security upgrade node-sass from 4.14.1 to 6.0.1 [#5789](https://github.com/ChurchCRM/CRM/pull/5789)
- Update PHP Mailer  [#5784](https://github.com/ChurchCRM/CRM/pull/5784)
- Bump hosted-git-info from 2.7.1 to 2.8.9 [#5761](https://github.com/ChurchCRM/CRM/pull/5761)
- Bump underscore from 1.9.1 to 1.13.1 [#5754](https://github.com/ChurchCRM/CRM/pull/5754)
- Github Action: update-demo-site [#5406](https://github.com/ChurchCRM/CRM/issues/5406)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)
- [@dependabot[bot]](https://github.com/apps/dependabot)

## [4.4.3](https://github.com/ChurchCRM/CRM/releases/tag/4.4.3) - 2021-05-31

This is a security update to fix phpmailer 

## :speech_balloon: Localization

- Locale cleanup [#5765](https://github.com/ChurchCRM/CRM/pull/5765)

## :gear: Inner Beauty

- Update grunt-cli [#5769](https://github.com/ChurchCRM/CRM/pull/5769)
- new phpmailer/phpmailer version [#5766](https://github.com/ChurchCRM/CRM/pull/5766)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)

## [4.4.2](https://github.com/ChurchCRM/CRM/releases/tag/4.4.2) - 2021-05-09

This is a localization + fix for the db upgrade bug.

## :speech_balloon: Localization

- 4.4.2 POEditor Update - pt_BR [#5752](https://github.com/ChurchCRM/CRM/pull/5752)

## :beetle: Bugs

- Bug: Send Newsletter display [#5751](https://github.com/ChurchCRM/CRM/pull/5751)

## :gear: Inner Beauty

- Build: 4.4.2 [#5748](https://github.com/ChurchCRM/CRM/pull/5748)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)

## [4.4.1](https://github.com/ChurchCRM/CRM/releases/tag/4.4.1) - 2021-05-02

This is a re-release of 4.4.0 with bug fix for new installs

# 4.4.1 Changes
## :speech_balloon: Localization

- Run POEditor Locales Action Daily  [#5739](https://github.com/ChurchCRM/CRM/issues/5739)

## :beetle: Bugs

- Update to 4.4.0 - database upgrade failed : Unable to execute INSERT statement [#5743](https://github.com/ChurchCRM/CRM/issues/5743)

## :gear: Inner Beauty

- Bump ssri from 6.0.1 to 6.0.2 [#5745](https://github.com/ChurchCRM/CRM/pull/5745)
- Starting 4.4.1 [#5741](https://github.com/ChurchCRM/CRM/pull/5741)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)
- [@dependabot[bot]](https://github.com/apps/dependabot)

# 4.4.0 Changes

## :speech_balloon: Localization

- 4.4.0 POEditor Update - s_ES [#5737](https://github.com/ChurchCRM/CRM/pull/5737)
- 4.4.0 POEditor Update - zh_CN [#5736](https://github.com/ChurchCRM/CRM/pull/5736)
- 4.4.0 POEditor Update - Others [#5731](https://github.com/ChurchCRM/CRM/pull/5731) [#5693](https://github.com/ChurchCRM/CRM/pull/5693)

## :beetle: Bugs

- Bigfix/person edit fb [#5730](https://github.com/ChurchCRM/CRM/pull/5730)
- Bug: Missing footer on login page & show password [#5714](https://github.com/ChurchCRM/CRM/pull/5714)

## :gear: Inner Beauty

- Build: 4.4.0 - JS & PHP Lib updates [#5715](https://github.com/ChurchCRM/CRM/pull/5715)
- Setup for Docker-based development environment [#5704](https://github.com/ChurchCRM/CRM/issues/5704)
- Docker/4.4.0 [#5712](https://github.com/ChurchCRM/CRM/pull/5712)
- Docker testing [#5699](https://github.com/ChurchCRM/CRM/pull/5699)
- [Snyk] Upgrade jquery from 3.5.1 to 3.6.0 [#5718](https://github.com/ChurchCRM/CRM/pull/5718)
- [Snyk] Upgrade i18next from 19.8.7 to 19.8.8 [#5694](https://github.com/ChurchCRM/CRM/pull/5694)
- Bump elliptic from 6.5.2 to 6.5.4 [#5692](https://github.com/ChurchCRM/CRM/pull/5692)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@brianteeman](https://github.com/brianteeman)
- [@lbridgman](https://github.com/lbridgman)
- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)
- [@dependabot[bot]](https://github.com/apps/dependabot)

## [4.3.2](https://github.com/ChurchCRM/CRM/releases/tag/4.3.2) - 2021-03-02

## :star: Enhancements

- Pledge Editor Markup [#5673](https://github.com/ChurchCRM/CRM/pull/5673)
- No default skin [#5664](https://github.com/ChurchCRM/CRM/pull/5664)
- font color [#5663](https://github.com/ChurchCRM/CRM/pull/5663)
- Invalid markup on dashboard [#5660](https://github.com/ChurchCRM/CRM/pull/5660)
- Clarify the display of an admin user permissions [#5658](https://github.com/ChurchCRM/CRM/pull/5658)
- typo on skin selection [#5656](https://github.com/ChurchCRM/CRM/pull/5656)
- Ensure not Insure [#5654](https://github.com/ChurchCRM/CRM/pull/5654)

## :speech_balloon: Localization

- Language LOCALE selection improvements  [#5639](https://github.com/ChurchCRM/CRM/pull/5639)
- Language LOCALE does not work using gettext  but i18n works in javascript.  [#5637](https://github.com/ChurchCRM/CRM/issues/5637)
- 4.3.2 POEditor Update - 2021-03-01 [#5684](https://github.com/ChurchCRM/CRM/pull/5684)
- 4.3.2 POEditor Update - 2021-02-23 [#5649](https://github.com/ChurchCRM/CRM/pull/5649)
- 4.3.2 POEditor Update - 2021-02-21 [#5646](https://github.com/ChurchCRM/CRM/pull/5646)
- 4.3.2 POEditor Update - 2021-02-16 [#5640](https://github.com/ChurchCRM/CRM/pull/5640)
- 4.3.2 POEditor Update - 2021-02-11 [#5636](https://github.com/ChurchCRM/CRM/pull/5636)
- 4.3.2 POEditor Update - 2021-02-07 [#5613](https://github.com/ChurchCRM/CRM/pull/5613)
- 4.3.2 POEditor Update - 2020-12-10 [#5612](https://github.com/ChurchCRM/CRM/pull/5612)


Language | Translations | Percentage
-- | -- | --
Vietnamese | 1 581 | 66.40%
Arabic | 116 | 4.87%
Indonesian | 73 | 3.07%
Polish | 48 | 2.02%
German | 25 | 1.05%
Slovenian | 16 | 0.67%
Russian | 10 | 0.42%
Estonian | 5 | 0.21%
Spanish | 3 | 0.13%




## :beetle: Bugs

- Family view error [#5625](https://github.com/ChurchCRM/CRM/issues/5625)

## :gear: Inner Beauty

- Delete php.ini_REGISTER_GLOBALS_OFF [#5681](https://github.com/ChurchCRM/CRM/pull/5681)
- Update ReportFunctions.php [#5679](https://github.com/ChurchCRM/CRM/pull/5679)
- [Snyk] Security upgrade i18next from 19.8.4 to 19.8.5 [#5643](https://github.com/ChurchCRM/CRM/pull/5643)
- [Snyk] Upgrade i18next from 19.8.6 to 19.8.7 [#5650](https://github.com/ChurchCRM/CRM/pull/5650)
- [Snyk] Upgrade i18next from 19.8.5 to 19.8.6 [#5648](https://github.com/ChurchCRM/CRM/pull/5648)
- [Snyk] Security upgrade jquery-validation from 1.19.2 to 1.19.3 [#5633](https://github.com/ChurchCRM/CRM/pull/5633)
- [Snyk] Upgrade bootbox from 5.5.1 to 5.5.2 [#5617](https://github.com/ChurchCRM/CRM/pull/5617)
- Bump ini from 1.3.5 to 1.3.7 [#5615](https://github.com/ChurchCRM/CRM/pull/5615)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@brianteeman](https://github.com/brianteeman)
- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)
- [@dependabot[bot]](https://github.com/apps/dependabot)

Github Changes: https://github.com/ChurchCRM/CRM/milestone/130?closed=1
Sha1: d77c5b259e0ac373707bce1ccdbccac6f106713e

## [4.3.1](https://github.com/ChurchCRM/CRM/releases/tag/4.3.1) - 2020-12-10

Locale Translations, Security updates & Bugfix Release

## :speech_balloon: Localization

New Terms updated

Language | Translations | Percentage
-- | -- | --
French | 68 | 2.86%
Portuguese (BR) | 57 | 2.39%
Chinese (TW) | 1 | 0.04%

## :bug: Bug
- Docker is now using PHP 7.4.13 [#5600](https://github.com/ChurchCRM/CRM/pull/5600)

## :gear: Inner Beauty

- Dev: import optimization and cleanup [#5596](https://github.com/ChurchCRM/CRM/pull/5596)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)

## [4.3.0](https://github.com/ChurchCRM/CRM/releases/tag/4.3.0) - 2020-11-30

## :pray: Feature Requests

- Feature/country state downdown [#5587](https://github.com/ChurchCRM/CRM/pull/5587)
- Feature: Individual User locale  [#5577](https://github.com/ChurchCRM/CRM/pull/5577)
- Feature/user settings tables [#5574](https://github.com/ChurchCRM/CRM/pull/5574)
- Feature: User settings - DB & API [#5567](https://github.com/ChurchCRM/CRM/pull/5567)
- Family Verification Email Body [#1723](https://github.com/ChurchCRM/CRM/issues/1723)
- Store default list size [#5191](https://github.com/ChurchCRM/CRM/issues/5191)

## :star: Enhancements

- Outgoing emails look outdated [#5557](https://github.com/ChurchCRM/CRM/issues/5557)
- Menu - People -> View all Persons [#5323](https://github.com/ChurchCRM/CRM/issues/5323)
- Every API request does 2 version checks  [#4992](https://github.com/ChurchCRM/CRM/issues/4992)
- Use system Country States for UI forms  [#4403](https://github.com/ChurchCRM/CRM/issues/4403)
- number of items in lists [#2144](https://github.com/ChurchCRM/CRM/issues/2144)

## :speech_balloon: Localization

- 4.2.3 POEditor Update - 2020-11-21 [#5559](https://github.com/ChurchCRM/CRM/pull/5559)
- See All People Translation issue [#5040](https://github.com/ChurchCRM/CRM/issues/5040)
- Outgoing Emails are not localized  [#5558](https://github.com/ChurchCRM/CRM/issues/5558)

## :beetle: Bugs

- Feature/user settings fin table [#5572](https://github.com/ChurchCRM/CRM/pull/5572)
- Bug: fixed older style log methods [#5562](https://github.com/ChurchCRM/CRM/pull/5562)
- PHP Strict Mode error [#5003](https://github.com/ChurchCRM/CRM/issues/5003)

## :gear: Inner Beauty

- Bug/fix api user key [#5584](https://github.com/ChurchCRM/CRM/pull/5584)
- Fixed spelling for iDashboardServiceIntervalTime [#5582](https://github.com/ChurchCRM/CRM/pull/5582)
- Code/refactor admin user api [#5581](https://github.com/ChurchCRM/CRM/pull/5581)
- moving to vonage from nexmo [#5492](https://github.com/ChurchCRM/CRM/pull/5492)
- Move Vonage from Nexmo [#5490](https://github.com/ChurchCRM/CRM/issues/5490)
- Review Usage of initial.js [#5453](https://github.com/ChurchCRM/CRM/issues/5453)
- Typo in System Setting [#5031](https://github.com/ChurchCRM/CRM/issues/5031)
- replace userconfig_ucfg usage with User Object methods. [#1466](https://github.com/ChurchCRM/CRM/issues/1466)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)

## [4.2.3](https://github.com/ChurchCRM/CRM/releases/tag/4.2.3) - 2020-11-22

Released on (11.21.2020)

Locale Translations, Security updates & Bugfix Release

## :star: Enhancements

- Update left menu to use People [#5464](https://github.com/ChurchCRM/CRM/pull/5464)
- Removed old Menu.php dashboard [#5438](https://github.com/ChurchCRM/CRM/pull/5438)

## :speech_balloon: Localization

| Language |	Translations	| Percentage |
|---|---|---|
| Norwegian Bokmål	| 112	| 4.72% |
| Romanian |	78	| 3.29% |
| Russian| 	65	| 2.74%|
| Arabic	| 11| 	0.46%|
| Spanish	| 2	| 0.08%|

## :beetle: Bugs

- Dashboard: Fix Person/Family button links [#5551](https://github.com/ChurchCRM/CRM/pull/5551)
- moved log line due to NPE [#5548](https://github.com/ChurchCRM/CRM/pull/5548)
- NPE: PropertyAPIMiddleware [#5547](https://github.com/ChurchCRM/CRM/issues/5547)
- pageName is now REQUEST_URI [#5523](https://github.com/ChurchCRM/CRM/pull/5523)
- Bug report does not work with v2 pages [#5413](https://github.com/ChurchCRM/CRM/issues/5413)

## :gear: Inner Beauty

- Removed bEnabledDashboardV2 Setting [#5517](https://github.com/ChurchCRM/CRM/pull/5517)
- [Snyk] Upgrade chart.js from 2.9.3 to 2.9.4 [#5515](https://github.com/ChurchCRM/CRM/pull/5515)
- [Snyk] Upgrade react-datepicker from 2.10.0 to 2.16.0 [#5514](https://github.com/ChurchCRM/CRM/pull/5514)

- Create Automated API Tests [#3998](https://github.com/ChurchCRM/CRM/issues/3998)
- Build: Fix travis & Cypress tests [#5520](https://github.com/ChurchCRM/CRM/pull/5520)
- Updated composer platform to 7.3 [#5494](https://github.com/ChurchCRM/CRM/pull/5494)
- Build: monolog upgrade [#5493](https://github.com/ChurchCRM/CRM/pull/5493)
- Build fails on composer 2 [#5489](https://github.com/ChurchCRM/CRM/issues/5489)

## [4.2.2](https://github.com/ChurchCRM/CRM/releases/tag/4.2.2) - 2020-11-03

## :speech_balloon: Localization

- 4.2.2 POEditor Update & Latest Locale download [#5487](https://github.com/ChurchCRM/CRM/issues/5487)

## :beetle: Bugs

- Locked Email Not sending [#5485](https://github.com/ChurchCRM/CRM/issues/5485)
- Bug: adding location as URL param does not work [#5483](https://github.com/ChurchCRM/CRM/pull/5483)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)

## [4.2.1](https://github.com/ChurchCRM/CRM/releases/tag/4.2.1) - 2020-10-28

## :star: Enhancements

- User Menu: Change Settings updated [#5471](https://github.com/ChurchCRM/CRM/pull/5471)

## :speech_balloon: Localization

- Locale/2.4.1v1 [#5467](https://github.com/ChurchCRM/CRM/pull/5467)
- 4.2.1: Locale Update [#5466](https://github.com/ChurchCRM/CRM/issues/5466)
- Locale % broken post 4.2.0 upgrade [#5463](https://github.com/ChurchCRM/CRM/issues/5463)

## :gear: Inner Beauty

- Log CSP Error/Events only if the system is in debug mode [#5473](https://github.com/ChurchCRM/CRM/pull/5473)
- Removed initial-js as it was not used [#5470](https://github.com/ChurchCRM/CRM/pull/5470)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)

## [4.2.0](https://github.com/ChurchCRM/CRM/releases/tag/4.2.0) - 2020-10-19


## :exclamation: Support Alert
> We are no longer testing with PHP 7.2; [End of life in Nov 2020](https://www.php.net/supported-versions.php)

## :star: Enhancements

- New: /v2/dashbaord page [#5446](https://github.com/ChurchCRM/CRM/pull/5446)
- Feature/enable v2 dashboard via settings [#5459](https://github.com/ChurchCRM/CRM/pull/5459)
- API reduction / speed  [#5447](https://github.com/ChurchCRM/CRM/pull/5447)
- Created new APIs for use on Main Dashbaord [#5443](https://github.com/ChurchCRM/CRM/pull/5443)
- Export to CVS does not include family/person ids [#5404](https://github.com/ChurchCRM/CRM/issues/5404)
- Active/Inactive Persons [#5088](https://github.com/ChurchCRM/CRM/issues/5088)
- Application Prerequisites: PHP 7.3+  [#5424](https://github.com/ChurchCRM/CRM/pull/5424)
- Add PHP 7.4 for Travis Testing [#5426](https://github.com/ChurchCRM/CRM/pull/5426)


## :speech_balloon: Localization

- 4.2.0 Update locale from POEditor [#5398](https://github.com/ChurchCRM/CRM/issues/5398)

## :beetle: Bugs

- Bug/list active people links [#5454](https://github.com/ChurchCRM/CRM/pull/5454)
- Fix $_SESSION["user"] usage [#5418](https://github.com/ChurchCRM/CRM/pull/5418)
- Error with Person view for non-admin [#5417](https://github.com/ChurchCRM/CRM/issues/5417)
- 4.2.0 POEditor Update - 2020-10-14 [#5416](https://github.com/ChurchCRM/CRM/pull/5416)
- User Setup tab case issue [#5415](https://github.com/ChurchCRM/CRM/issues/5415)
- fix JS error #5412 [#5414](https://github.com/ChurchCRM/CRM/pull/5414)
- JS error on Family View [#5412](https://github.com/ChurchCRM/CRM/issues/5412)
- MailChimp: 412 (Precondition Failed) [#5411](https://github.com/ChurchCRM/CRM/issues/5411)
- replaced broken chat JS with new version [#5410](https://github.com/ChurchCRM/CRM/pull/5410)
- Deposit Tracking - Chart broken [#5408](https://github.com/ChurchCRM/CRM/issues/5408)
- 404 - pdfmake.min.js.map [#5432](https://github.com/ChurchCRM/CRM/issues/5432)

## :gear: Inner Beauty


- Github Action: update & audit npm and composer  [#5401](https://github.com/ChurchCRM/CRM/issues/5401)
- Github Action: Automate POEditor file download [#5400](https://github.com/ChurchCRM/CRM/issues/5400)
- Github Action: Generate Release notes from milesone [#5399](https://github.com/ChurchCRM/CRM/issues/5399)
- Build: PHP composer lib updates [#5451](https://github.com/ChurchCRM/CRM/pull/5451)
- Upgrade of Travis CI Build/Test Env [#5449](https://github.com/ChurchCRM/CRM/pull/5449)
- Fix SLIM displayErrorDetails setting [#5444](https://github.com/ChurchCRM/CRM/pull/5444)
- Remove PHP 7.2 support  [#5403](https://github.com/ChurchCRM/CRM/issues/5403)
- 4.2.0 Upgrade JS and PHP libs to latest minor/patch [#5402](https://github.com/ChurchCRM/CRM/issues/5402)
- CHANGELOG.md is out of date [#5395](https://github.com/ChurchCRM/CRM/issues/5395)
- Create Release Notes via Actions [#5389](https://github.com/ChurchCRM/CRM/pull/5389)
- Build Updates [#5382](https://github.com/ChurchCRM/CRM/pull/5382)
- Action to Automate POEditor Update and Download [#5370](https://github.com/ChurchCRM/CRM/pull/5370)
- Test support for PHP 7.4 [#5334](https://github.com/ChurchCRM/CRM/issues/5334)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)
- [@crossan007](https://github.com/crossan007)
- [@MrClever](https://github.com/MrClever) 
- [POEditor.com Localization Team](https://poeditor.com/contributors/?id_project=77079)

[See complete change log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md)

## SHA1 
c9207ac8b05fb69458f91fc90ccea3969f37c56c

## [4.1.4](https://github.com/ChurchCRM/CRM/releases/tag/4.1.4) - 2020-10-11

This release that addresses the following issues:

*Features:*

- Downloaded updated locales terms from POEditor
- Support Afrikaans as a locale
- API to support People/Family properties

Bug fixes:

- Fix invalid session name
- inputmask JS now works
- Family Properties now can be added again

Github Changes: https://github.com/ChurchCRM/CRM/milestone/124?closed=1

SHA1: a34f4b36dde6e52de6eeaa46a670e77c17414162

## [4.1.3](https://github.com/ChurchCRM/CRM/releases/tag/4.1.3) - 2020-09-24

This release that addresses the following issues:

**Features:**

Added a flag to match the current system locale
display the selected locale poeditor.com localization %
links to POEditor to allow more people to help complete the localization.
Downloaded updated locales terms from POEditor

**Bug fixes:**

"Events" -> "Event Attendance Reports" not working
Updated Family Pledge Summary report to be localized.
Fixed de_DE locale terms in POEditor that were negatively impacting GroupVIew
Calendar and 2FA terms are now part of the POEditor system.
People Search Gender and other terms were not localized.

Github Changes: https://github.com/ChurchCRM/CRM/milestone/123?closed=1

SHA1: 02d6de99b7d9ad5373653c8395e6d87afe879c3a

## [4.1.1](https://github.com/ChurchCRM/CRM/releases/tag/4.1.1) - 2020-08-08

This release that addresses the following issues:

Changes:
* Fixed issues with creating an event via the calendar
* Updated Locale files see below table

Locale | Translations | Percentage
-- | -- | --
sq | 827 | 35%
ar | 2271 | 97%
zh-cn | 2140 | 91%
zh-tw | 2301 | 98%
cs | 0 | 0%
nl | 1780 | 76%
en | 11 | 0%
en-au | 22 | 0%
en-ca | 18 | 0%
en-us | 0 | 0%
et | 1663 | 71%
fr | 2228 | 95%
de | 2115 | 90%
he | 232 | 9%
hu | 832 | 35%
id | 2271 | 97%
it | 2197 | 94%
ja | 0 | 0%
nb | 831 | 35%
pl | 835 | 35%
pt | 2280 | 97%
pt-br | 2280 | 97%
ro | 1703 | 73%
ru | 869 | 37%
es | 2301 | 98%
sv | 2301 | 98%
th | 59 | 2%
tr | 2 | 0%
vi | 810 | 34%

Github Changes: https://github.com/ChurchCRM/CRM/milestone/121?closed=1

SHA1: bba10ee5eb23e24b0e67ae1bf1d9df38cc2027ed

## [4.1.0](https://github.com/ChurchCRM/CRM/releases/tag/4.1.0) - 2020-06-18

This release that addresses the following issues:

*Features*
- View User page now displays permissions & allows for Skin selector via examples
- System Users page now has inline read-only config display
- Locale Updates from POEditor.com
- Added a new tab on Family / Person to show MailChimp status
- New Pages for MailChimp lists to display delta between the 2 systems 

*Bugs:*
- Family Online verify bug addressed 

*Inner beauty:*
- moved all family links to v2 page and removed the redirectors
- Removed the use of Views to ensure new installs are error-free
- Removed redundant db indexes. 

Github Changes: https://github.com/ChurchCRM/CRM/milestone/119?closed=1

SHA1: 544261d7dda026a61235b4d841c9c30eadea8e87

## [4.0.5](https://github.com/ChurchCRM/CRM/releases/tag/4.0.5) - 2020-06-05

This is a minor release that addresses the following issues:

*Features*
-Locale Updates from POEditor.com
-Added Hebrew as a language


Language | Translations | Percentage
-- | -- | --
Estonian | 425 | 18.50%
Ukrainian | 92 | 4.01%
Portuguese | 87 | 3.79%
Romanian | 81 | 3.53%
Portuguese (BR) | 66 | 2.87%
Spanish | 30 | 1.31%
Russian | 28 | 1.22%



*Inner beauty:*
- Security updates
- POEditor Audit Script
- Updated Select2 lib
- Updated Chart.js lib

Github Changes: https://github.com/ChurchCRM/CRM/milestone/118?closed=1

SHA1: f7da4cb9f0221262c0921c8a0c666fbe0cae2802

## [4.0.4](https://github.com/ChurchCRM/CRM/releases/tag/4.0.4) - 2020-05-21

This is a minor bugfix release that addresses the following issues:

*  #5216 - Remove undesired whitespace & clarify purpose of Month/Day/Year fields in Family Editor 
*  #5229 - Bump jquery from 3.4.1 to 3.5.0 
*  #5224 / #5230 - API tokens don't work
*  #5179 - Slim application error doing Self Registration (Missing packages from release zip)

SHA1: D3F1FFB8700CCD60D5686B49212F857A1A8E4871

## [4.0.3](https://github.com/ChurchCRM/CRM/releases/tag/4.0.3) - 2020-04-01

This is a bugfix release which addresses the following issues:

#5205 - Groups is a reserved keyword in MySQL 8.0
#5107 - Latitude / Longitude refreshes don't always work
#5184 - Self-verification URLs missing a slash between segments
#5174 - Google Maps now uses different keys for Geocoding vs JS Maps API
#5178 - Self-service password reset works again after the 4.x.x upgrade.  (Password reset logging is better too)
#5175 - Deleting users shows an error message
#5175 - Added a new system config to control whether deleted users are sent a confirmation email (defaults to FALSE)
#5141 - Updated the "Admin Task Help Links" - specifically around the "Secrets" configuration introduced with 4.x.x

NOTE: If you are using the Google Maps API keys, you will need to re-enter your API keys after installing this release.  Previous versions of ChurchCRM did not separate the geocoding keys from the JavaScript maps keys (which is against Google's best practice recommendations).  Please review the ChurchCRM geographic wiki for more details: https://github.com/ChurchCRM/CRM/wiki/Geographic

SHA1 Hash: E927628711954338CBD87FA2633A338AA70BE749

## [4.0.2](https://github.com/ChurchCRM/CRM/releases/tag/4.0.2) - 2020-02-20

This is a bugfix release which primarily addresses issues around new installations of ChurchCRM:

*  Closes the "clean install issue" with missing columns in the user table: #5146 and #5164
*  Fixes an issue where timerjobs were failing on newer versions of PHP: #5159

Milestone closed: https://github.com/ChurchCRM/CRM/milestone/114?closed=1

SHA1: 555B4B0A4876B7DC8EB7DDFEC92F3CDE15885D24

## [4.0.1](https://github.com/ChurchCRM/CRM/releases/tag/4.0.1) - 2020-02-17

This is a bugfix release that addresses some issues found in the 4.0.0 release:

*  Password change does not work (#5145)
*  Newly created events do not persist the event type (#4149)

Since 4.0.0 was only a pre-release, below are the release notes for 4.0.0:
==============================================
IMPORTANT: Ensure you have the necessary version of PHP to run this release (PHP7.1+)

This fixes many bugs, and introduces a new extensible authentication system with support for two factor authentication.

Notable bug fixes:

Bad link on V2 family page (#5137)
Missing arrows in DataTables (#5131)
HTTP 404 for bootstrap-timepicker (#5127)
Events created without a pinned calendar now appear on the calendar (#4830)
Searching within family custom properties now works (#5039)
Fixes to the V2 Family List (#5115, #5116)
Number "12" showing up after birthdays with age (#5079)

==============================================

SHA1: DB8DEB3CDFB0613E5C1E2705122DCF4CC726F447

## [3.5.5](https://github.com/ChurchCRM/CRM/releases/tag/3.5.5) - 2019-10-25

## *Features:* 
- Improved Family Self Registration 
- Updated Locale from POEditor.com  
Github Changes: https://github.com/ChurchCRM/CRM/milestone/112?closed=1 

SHA1: 0b127a5b051e500ca46239caaf0eeef0e671d136

[5.22.0]: https://github.com/ChurchCRM/CRM/compare/5.21.0...5.22.0
