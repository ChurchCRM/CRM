# 4.2.3
Released on (11.21.2020)
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

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@snyk-bot](https://github.com/snyk-bot)
- [@dzidek23](https://github.com/dzidek23)
- [@DawoudIO](https://github.com/DawoudIO)
- [@github-actions[bot]](https://github.com/apps/github-actions)

# 4.2.2
Released on (11.3.2020)
## :speech_balloon: Localization

- 4.2.2 POEditor Update & Latest Locale download [#5487](https://github.com/ChurchCRM/CRM/issues/5487)

## :beetle: Bugs

- Locked Email Not sending [#5485](https://github.com/ChurchCRM/CRM/issues/5485)
- Bug: adding location as URL param does not work [#5483](https://github.com/ChurchCRM/CRM/pull/5483)

## :gear: Inner Beauty

- Starting 4.2.2 [#5480](https://github.com/ChurchCRM/CRM/pull/5480)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)

# 4.2.1
Released on (10.28.2020)

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

# 4.2.0
Released on (10.19.2020)

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

# 4.1.4
Released on (10.10.2020)

## :speech_balloon: Localization

- Support Afrikaans as a locale [#5354](https://github.com/ChurchCRM/CRM/issues/5354)

## :beetle: Bugs

- Fresh install reloads login page [#5363](https://github.com/ChurchCRM/CRM/issues/5363)
- 404 for Inputmask files [#5350](https://github.com/ChurchCRM/CRM/issues/5350)
- Family Properties Problems [#5347](https://github.com/ChurchCRM/CRM/issues/5347)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)


# 4.1.2
Released on (09.24.2020)

## :tada: Core Functionality

- Display locale % and links to POEditor in the system so that people can help update [#5337](https://github.com/ChurchCRM/CRM/issues/5337)
- Event Attendance Reports not working [#4790](https://github.com/ChurchCRM/CRM/issues/4790)

## :speech_balloon: Localization

- Typo / Localization  [#5331](https://github.com/ChurchCRM/CRM/issues/5331)
- Use lipis/flag-icon-css [#5322](https://github.com/ChurchCRM/CRM/issues/5322)
- Select Gender can't change in my language [#5320](https://github.com/ChurchCRM/CRM/issues/5320)
- Missing translations in reports. [#5311](https://github.com/ChurchCRM/CRM/issues/5311)
- GroupView not working with \"Localization > sLanguage=de_DE\" [#5220](https://github.com/ChurchCRM/CRM/issues/5220)
- React JS terms are not captured in upload to POEditor [#5139](https://github.com/ChurchCRM/CRM/issues/5139)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)

# 4.1.1
Released on (08.08.2020)

## :beetle: Bugs

- Wrong Bootstrap version in react-bootstrap [#5292](https://github.com/ChurchCRM/CRM/issues/5292)
- Calendar Broken [#5291](https://github.com/ChurchCRM/CRM/issues/5291)
- Create events from Calendar broken [#5287](https://github.com/ChurchCRM/CRM/issues/5287)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)
- [@crossan007](https://github.com/crossan007)

# 4.1.0
Released on (06.18.2020)

## :star: Enhancements

- Move to MailChimp v3 APIs [#998](https://github.com/ChurchCRM/CRM/issues/998)

## :tada: Core Functionality

- MailChimp is blank [#5269](https://github.com/ChurchCRM/CRM/issues/5269)
- Bug/mailchip ajax cleanup [#5267](https://github.com/ChurchCRM/CRM/pull/5267)

## :beetle: Bugs

- Mailchimp is missing [#5268](https://github.com/ChurchCRM/CRM/issues/5268)

## :gear: Inner Beauty

- Chart.JS is way out of date [#4690](https://github.com/ChurchCRM/CRM/issues/4690)
- Remove redundant indexes for MySQL [#2561](https://github.com/ChurchCRM/CRM/issues/2561)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)
- [@crossan007](https://github.com/crossan007)

# 4.0.5
Released on (06.05.2020)

## :tada: Core Functionality

- Bug update select2 to select2.full needed [#5221](https://github.com/ChurchCRM/CRM/issues/5221)

## :speech_balloon: Localization

- Support Hebrew Locale [#5396](https://github.com/ChurchCRM/CRM/issues/5396)
- Logging Timezones not consistent [#5135](https://github.com/ChurchCRM/CRM/issues/5135)

## :gear: Inner Beauty

- May 2020 Security updates [#5397](https://github.com/ChurchCRM/CRM/issues/5397)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@rbeerster](https://github.com/rbeerster)
- [@DawoudIO](https://github.com/DawoudIO)
- [@crossan007](https://github.com/crossan007)

# 4.0.4
Released on (04.20.2020)

## :star: Enhancements

- API_Request failed [#5224](https://github.com/ChurchCRM/CRM/issues/5224)

## :beetle: Bugs

- Slim application error doing Self Registration [#5179](https://github.com/ChurchCRM/CRM/issues/5179)

## :gear: Inner Beauty

- Bump jquery from 3.4.1 to 3.5.0 [#5229](https://github.com/ChurchCRM/CRM/pull/5229)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@meichthys](https://github.com/meichthys)
- [@crossan007](https://github.com/crossan007)
- [@dependabot[bot]](https://github.com/apps/dependabot)

# 4.0.3
Released on (03.31.2020)

## :star: Enhancements

- Logitude Latitude necessary for mapping function to work?  Available outside of US? [#5174](https://github.com/ChurchCRM/CRM/issues/5174)

## :tada: Core Functionality

- Family demographic verification process URL error [#5184](https://github.com/ChurchCRM/CRM/issues/5184)
- Delete of system user works but gives a large error message [#5175](https://github.com/ChurchCRM/CRM/issues/5175)
- Unable to automatically update the Geo LAT and Long said 0 missing [#5107](https://github.com/ChurchCRM/CRM/issues/5107)

## :beetle: Bugs

- MySQL Reserved Word Error [#5205](https://github.com/ChurchCRM/CRM/issues/5205)
- "I forgot my password" at the log in fails with error message [#5173](https://github.com/ChurchCRM/CRM/issues/5173)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@rbeerster](https://github.com/rbeerster)
- [@crossan007](https://github.com/crossan007)

# 4.0.2
Released on (02.20.2020)

## :star: Enhancements

- Background Backup Jobs Failing [#5080](https://github.com/ChurchCRM/CRM/issues/5080)

## :beetle: Bugs

- Cannot Load Interface After Initial Setup [#5164](https://github.com/ChurchCRM/CRM/issues/5164)
- Backup Timerjobs API throwing error 500 preventing uprgade [#5159](https://github.com/ChurchCRM/CRM/issues/5159)
- The installer does not recognize file permissions Include/Config and Images, when fixed and finished the installer login got stuck on the page localhost/setup [#5146](https://github.com/ChurchCRM/CRM/issues/5146)

# 4.0.1
Released on (02.17.2020)

## :tada: Core Functionality

- Event Designation Error [#5149](https://github.com/ChurchCRM/CRM/issues/5149)

## :beetle: Bugs

- Password change page is broken [#5145](https://github.com/ChurchCRM/CRM/issues/5145)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@crossan007](https://github.com/crossan007)

# 4.0.0
Released on (01.30.2020)

## :star: Enhancements

- filter and sorting information [#5136](https://github.com/ChurchCRM/CRM/issues/5136)
- Filter Missing [#5112](https://github.com/ChurchCRM/CRM/issues/5112)
- Sunday School Dashboard [#5077](https://github.com/ChurchCRM/CRM/issues/5077)
- Edit Person - Family form [#5067](https://github.com/ChurchCRM/CRM/issues/5067)
- Error making API Call to: /api/search/raj [#5039](https://github.com/ChurchCRM/CRM/issues/5039)
- Unable to type date text on new event form [#4815](https://github.com/ChurchCRM/CRM/issues/4815)
- icons missing for custom roles in mapping [#4140](https://github.com/ChurchCRM/CRM/issues/4140)

## :tada: Core Functionality

- Birthdates Formatting Problem [#5151](https://github.com/ChurchCRM/CRM/issues/5151)
- Verify people doesn\'t prompt [#5125](https://github.com/ChurchCRM/CRM/issues/5125)
- Birthdays not showing on the Calendar [#5118](https://github.com/ChurchCRM/CRM/issues/5118)
- Birthdays not showing on the Calendar [#5117](https://github.com/ChurchCRM/CRM/issues/5117)
- Remove Donation/Payment [#5105](https://github.com/ChurchCRM/CRM/issues/5105)
- Cannot Add Events To Calendars [#4830](https://github.com/ChurchCRM/CRM/issues/4830)

## :beetle: Bugs

- empty directory [#5157](https://github.com/ChurchCRM/CRM/issues/5157)
- birthday not showup in calendar [#5156](https://github.com/ChurchCRM/CRM/issues/5156)
- DataTables images not rendering [#5131](https://github.com/ChurchCRM/CRM/issues/5131)
- Parenthesis in Classification Names breaks Person filtering by Classification [#5116](https://github.com/ChurchCRM/CRM/issues/5116)
- Apostrophe in Group name breaks Person Filter UI in Person listing [#5115](https://github.com/ChurchCRM/CRM/issues/5115)
- Birthdate display has 12 at the end of the age for every person that has a birthdate [#5079](https://github.com/ChurchCRM/CRM/issues/5079)
- Can\'t find route for GET [#5038](https://github.com/ChurchCRM/CRM/issues/5038)

## :gear: Inner Beauty

- Missing bootstrap-timepicker? [#5127](https://github.com/ChurchCRM/CRM/issues/5127)
- Build system including demo images [#5100](https://github.com/ChurchCRM/CRM/issues/5100)
- 4.0.0 release nearly doubled in size [#5099](https://github.com/ChurchCRM/CRM/issues/5099)
- Evaluate Propel Validators in Schema.xml [#5097](https://github.com/ChurchCRM/CRM/issues/5097)
- Update travisfile to use latests PHP for demo deploy [#4979](https://github.com/ChurchCRM/CRM/issues/4979)
- convert class const members to private after php7.0 removal [#4948](https://github.com/ChurchCRM/CRM/issues/4948)
- Composer 2.0: Deprecation warning [#4904](https://github.com/ChurchCRM/CRM/issues/4904)

## :heart: Contributors

We'd like to thank all the contributors who worked on this release!

- [@DawoudIO](https://github.com/DawoudIO)
- [@crossan007](https://github.com/crossan007)
