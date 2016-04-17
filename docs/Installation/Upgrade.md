#Upgrading from ChurchInfo

## Prerequisites:
* ChurchInfo database must be at functional level 1.2.14
* Create a valid backup of your ChurchInfo Database

##Side-by-side upgrade (preferred, easy)
The preferred method for upgrading from ChurchInfo is a side-by-side upgrade.  This affords you the most flexibility for testing and the least amount of risk when upgrading.

To perform a side-by-side upgrade from an existing ChurchInfo installation:

1. Install a new instance of ChurchCRM (follow the install instructions)
2. Backup your existing ChurchInfo database.
    * You may need to enable the backup feature by setting (bEnableBackupUtility) to True in the "Edit }General Settings" page.
    1. From the "Admin" menu, choose "Backup Database"
    2. Select archive type "uncompressed"
    3. Do not encrypt the backup
    3. Click "Generate and Download Backup"
3. Restore your backup to ChurchCRM
    1. From the Admin (gear) menu, choose "Restore Database"
    2. Click "Choose Files," and select the backup file created in step 2.
4. Click "Upload Files"
5. When "Restore Status" changes to "Restore Complete," click on the "Login to restored Database" button
6. Login to the database using your previous username and password
7. Validate that the upgrade was successful.
8. De-provision your old instance of ChurchInfo when ready.


##In-place upgrade (not recommended, difficult)
This method is not recommended, and could result in total data loss!  Please make sure you have a backup of your data!

To perform an in-place upgrade on an existing ChurchInfo installation:

1. Copy the following files to a temporary (safe) location:
    *  Include/Config.php
    *  Include/ReportConfig.php

2.  Delete the entire contents of your current ChurchInfo directory.
3.  Upload the entire churchCRM folder where ChurchInfo was.
4.  Upload the files stashed in step 1 back to their original locations, replacing any conflicts
3.  From a server Shell, execute the following MySQL scripts (in this order):
    1. src\mysql\upgrade\1.2.14-2.0.0.sql
    2. src\mysql\upgrade\rebuild_nav_menus.sql
6. Login to the database using your previous username and password
7. Validate that the upgrade was successful.
