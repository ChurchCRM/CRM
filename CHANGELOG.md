# Changelog

## 2.7.0-RC2 (08/04/2017)
Significant changes include:

User Management Improvements
Deposit Improvements
Map Improvements
Cart Improvements
Many bug fixes


See full changelog here: https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+milestone%3A2.7.0+is%3Aclosed
---

## 2.6.3 (30/03/2017)
Minor release

*  Updates jQuery Photo Uploader to v1.0.12 for improved mobile photo capture
*  Implements a system notification bar for push messages from ChurchCRM developers.

MD5: DF643A6CA6E69A1ACC625589D5C28F1D
SHA1:  55CEF19796F3F35C656C7C3A2EBFB11978E39957
SHA256: 1F8D7D8DEA9F687FD3C213B77DEFA8AB2E2E3DB0161E6FB90096DDBD6F90061C
---

## 2.6.2 (17/03/2017)
This is a bug fix release that addresses:

*  Backup errors
*  Event creation
*  Self Registration Country Data

And a few more.

See full Change Log here: https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md

MD5: B315160EEAE67117F54D8FA80EA42283
SHA1: 513D39CFC54C2DE15007F6E26236FEC420BFEF0D
SHA256: 0C6161D6E6BA9F785312A5034C3BD89E87A3C611259A1906419A7BDF3C349654

# Upgrading from 2.6.0 or 2.6.1:

If you're currently on 2.6.0 or 2.6.1, you'll need to patch SystemService in order to get an automatic upgrade: https://github.com/ChurchCRM/CRM/pull/2126

This involves updating line 109 of src/ChurchCRM/Service/SystemService.php to 
```
mkdir($backup->backupDir,0750,true);
```
instead of 
```
mkdir($backup->backupDir,0644,true);
```

---

## 2.6.1 (13/03/2017)
This is a patch release which addresses the following issues:

Bugs:
- Updated jquery photo uploader (#2035) 
- Global search box fixes 
-  Member Dashboard totals (#1925) 

Changes:
- New Person/Family Photo UI


See full Change Log here: https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md
---

## 2.6.0 (28/02/2017)
This is a new major build of ChurchCRM 2.6.0 

Significant changes include:

New Default Image System for Members
Upload new Images for Members via Web Cam (SSL/TLS Needed)
Downloaded updated transalation from POEditor
Calendar UX Changes
More Date localization FR/ES
Moving more pages to New Skin
Lots of other inner beauty changes.

See full changelog here: https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md

MD5: 0EB5A7BDEBA5F2E05F736683986279BC
SHA1: 3A2945158096C6B8C5C5FCB704117242B9B7F2EA
SHA256: 4909684CE663499D8A5856A5581D771CEDF24C03F88C464AFC94232868C29CA4

---

## 2.5.2 (28/01/2017)
This is a bugfix release which addresses the following issues:
- Calendar CSS / Print  (#1793)
- Menu sidebar dropdown arrows are misaligned (#1806)
- Import of translations from POEditor

New Feature:
- Add a Nav Task to check upload size

See full changelog here: https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md

---

## 2.5.1 (27/01/2017)
This is a bugfix release which addresses the following issues:
-  Family online verification (#1777)
-  In-App Bug reporting verbosity (#1773)
-  First click of data verification tasks fails (#1715)
-  Cleanup of dead email code (#1775)
-  Import of translations from POEditor

See full changelog here: https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md

MD5: 5A881603873285730D2802617A6E9E40
SHA1: E77FB212B0D4BA6CF86B8D1B5A03CD97938A4A34

---

## 2.5.0 (23/01/2017)
This is a new major build of ChurchCRM.

Significant changes include:
- Self Registration
- Better server prerequisite checking
- Improved build process
- Fixes to deposit reports
- Fixes to UTF-8 character sets

See full changelog here: https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md

MD5: 23824B87D1952AAFF78A964B73F165BD
SHA1: 054AB0D1D17D9D5D9EB5D31DBA2A47F02DFAC229

---

## 2.4.4 (07/01/2017)
- Update German & French locale files from poeditor.com 
- Enforced Code Style via StyleCI.com
- Ensured Code Quality via Travis-ci.com
- Fixed sHeader Header
- Fixed Person/Family create Note Bug
- Building a Zip, tar
- Including Demo Data.

---

## 2.4.3 (28/12/2016)
This is a bugfix release that addresses the following issues:
-  sPageTitle not rendering
-  Unable to add donation funds
-  Unable to view donations for a family from Family View page 

For a full list of changes, please see the [Changelog](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md)

---

## 2.4.2 (08/12/2016)
This is a bugfix build which restores in-application updates.

If you were previously on 2.4.0, or 2.4.1, you must manually replace the files on your server with the files contained in this release, as the built in updater was broken in those builds.

---

## 2.3.5 (17/11/2016)
This is a bug fix release which fixes the translation issues present in 2.3.3 and 2.3.4

---

## 2.3.4 (16/11/2016)
This is a bug fix release that addresses the following issues:
- Improved translation 
- New build system
- Data tables are now responsive
- Typos
- Misc other fixes.

---

## 2.3.3 (12/11/2016)
This is a bug fix release that addresses the following issues:
- Failure to upgrade from ChurchCRM versions earlier than 2.0.2
- Errors on the Family View page.
- Rendering issues on the Mail Dashboard
- Additional language translations 

---

## 2.3.2 (09/11/2016)
This is a minor bug fix release.

Fixes include:
-  Fix UTF (utf8mb4) for database entities
-  Include French translations
-  Improvements with Notes
-  Improvements with Calendar
-  Fixed issue creating events with languages other than English

---

## 2.3.1 (06/11/2016)
2.3.1 supersedes the release of 2.3.0, which had a few "showstopper" bugs.

This is a major upgrade from 2.2.4 with many new features and bug fixes:
-  Application integrity checks now periodically verify the source code of all application files
-  Family Self Registration - turned off by default; families can now "self-register" from the web
-  Improved Localization support.
-  Improved software registration process - please re-register your ChurchCRM install so we can get a better idea of how the software is being used
-  Many other bugfixes and general UI improvements

For all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?page=2&q=is%3Aissue+is%3Aclosed+milestone%3A2.3.0)

---

## 2.2.4 (16/10/2016)
This is a patch fix for a few bugs in 2.2.0, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.2.4)

---

## 2.2.3 (08/10/2016)
This is a patch fix for a few bugs in 2.2.0, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.2.3)

---

## 2.2.2 (02/10/2016)
This is a patch fix for a few bugs in 2.2.0, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.2.2)

---

## 2.2.1 (30/09/2016)
This is a patch fix for a few bugs in 2.2.0, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.2.1)

---

## 2.2.0 (17/09/2016)
This is a major release with logs of under the hood cleanup but here are some of the major changes in the release, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.2.0)
- New Person Photo logic used everywhere now
- Deposit Slip made easier
- Group management made easier
- Better MailChimp Interactions 
- Better Error handling for unsupported PHP/MySQL Versions 
- Sunday School Bug fixes
- Event Bug fixes
- Recommends Browser upgrades for users 
- Fix iOS Search Bar
- More Localization Support

---

## 2.1.11 (06/08/2016)
This is a patch fix for a few bugs in 2.1.x, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.1.11)

---

## 2.1.10 (03/08/2016)
This is a patch fix for a few bugs in 2.1.x, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.1.10)

---

## 2.1.8 (28/07/2016)
This is a patch fix for a few bugs in 2.1.x, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.1.8)

---

## 2.1.7 (11/07/2016)
This is a patch fix for a few bugs in 2.1.x, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.1.7)

---

## 2.1.6 (06/07/2016)
This is a patch fix for a few bugs in 2.1.x, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.1.6)

---

## 2.1.3 (26/06/2016)
This is a patch fix for a few bugs in 2.1.x, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.1.3)

---

## 2.1.2 (24/06/2016)
This is a patch fix for a few bugs in 2.1.x, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/master/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.1.2)

---

## 2.1.1 (21/06/2016)
This is a patch fix for a few bugs in 2.1.0, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/develop/CHANGELOG.md) and [Closed Bugs](https://github.com/ChurchCRM/CRM/issues?q=is%3Aissue+is%3Aclosed+milestone%3A2.1.1)

---

## 2.1.0 (18/06/2016)
The following are the main changes for this release, for all changes in the release, please review the [Change Log](https://github.com/ChurchCRM/CRM/blob/develop/CHANGELOG.md)

## Person View:
- Now timeline view in Person and Family 
- Event attendance information is now shows on timeline 
- History of Updates / Photo Changes now shows on timeline 
- New Family verification button that shows when that the family info were verified

## Calendar:
- Church Events now show up on Calendar

## New Dashboards
- Updated Email Dashboard
- Updated Sunday School Dashboard

## System
- New Tasks for incomplete setup
- New Task shown when a new version is released 
- System Setting now show as tabs
- System settings now have simple hits via a popup
- Gitter chat now is a header button
