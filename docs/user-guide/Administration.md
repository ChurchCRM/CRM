# Administration

The admin menu is accessible by clicking the ⚙ (gear) icon in the top right corner (see screenshot).

![Admin (gear) menu](images/gear_menu.png)

## How do I add new Users?

See the [Users](Users.md) help topic.

## How do I edit Users?

See the [Users](Users.md) help topic.

## What is the default password assigned to new Users?

See the [Users](Users.md) help topic.

## How do I configure automatic database backups?

In General Settings | System Settings, modify the following values:

* **sEnableExternalBackupTarget**
  If you enable the external backup target, the system will allow you to do automatic and manual backups to a remote location specified below.

* **sExternalBackupType**
  Presently ChurchCRM supports backing up to the local filesystem, and to a WebDAV share.  Enter either "Local" or "WebDAV" in this box.

* **sExternalBackupEndpoint**
  Enter either the local path, or the WebDAV URL here.  HTTPS is preferred for WebDAV.

* **sExternalBackupUsername**
  If using WebDAV, enter the WebDAV username here.

* **sExternalBackupPassword**
  If using WebDAV, enter the WebDAV password here.

* **sExternalBackupAutoInterval**
  If you'd like backups to occur automatically, enter the interval (in hours) at which you would like an automatic backup to take place.  ChurchCRM does not rotate backup files.  
  ChurchCRM evaulates whether to make a backup with each page request, so the interval between backups is not guaranteed.
