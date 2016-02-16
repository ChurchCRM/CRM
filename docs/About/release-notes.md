# Release Notes

---
## Version 2.0.0 (develop branch)

### UI

- AdminLTE admin nav/and pages 
- Search by Family, Person, Group from the upper left search box
- Replaced the Nav bar with bootstrap nav so that it has responsive ui in mobile devices
- Updated Family/Person action items to use Bootstrap buttons vs Links
- Added support for using the logged in user image via Gravatar URL
- Updated Login screen to use bootstrap
- Updated Family/Person Photo UI

### Email

- Support for MailChimp CRM Tools
- Display a list of all MailChimp Lists for an email (family/person view)
- Updated quick list search to search by email
- Added support to email a PDF copy of a family record to that family
- List emails not in the MailChimp Lists
- Export a CSV with person name, and emails for import into mail chimp

### Reports

- Added support to create a family PDF from the family view
- Email Every family in the CRM a copy of the family PDF
- Email a family PDF via email
- Export Sunday School CSV of the child info along with parental details

### Sunday School Groups

- New Nav Menu for Sunday School Kids
- Pages for Sunday School teachers to get full parents details about each kid
- Email parents from the Sunday School Group Page.

### Development and Tools

- Seed data for demo/testing
- Vagrant Box
- Docker Image
- Improved dependency handling

### System and Stability

- Begin moving all internal resources to an API model
- Improved database backup
- Web based database restore and upgrade