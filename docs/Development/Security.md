# Security

## User Accounts
Any "Person" in ChurchCRM may be promoted to a "User."  By default "Persons" in ChurchCRM are not able to log into the system at all until an administrator provisions their user account.

## Role Based Access Control
ChurchCRM contains many roles to control access to your church's sensitive data:
### Add Records

### Edit Records

### Delete Records

### Manage Properties and Classifications

### Manage Groups and Roles

### Manage Donations and Finance 

### View, Add, and Edit Notes

### Edit Self

### Canvasser

### Admin

## Granular Permissions
In addition to the above roles, the following actions are controlled granularly "Per-User":

###bEmailMailto

###sMailtoDelimiter

###bSendPHPMail

###sFromEmailAddress

###sFromName

###bCreateDirectory

###bExportCSV

###bUSAddressVerification

###bAddEvent

###bSeePrivacyData

## Developer Notes
Security is currently handled by $_SESSION variables.  If a user has been assigned a given role, the name of that role will be present as a key in the $_SESSION array.

```
if($_SESSION['bAdmin'])
{
  #This user has been assigned the Admin role
}
```
