To upgrade an existing ChurchInfo installation:

Check in the SQL folder for Update...sql files.  These files make changes
to your database so you can run a newer version of ChurchInfo.

Upgrading to 1.2.3:

Save copies of your existing Include/Config.php and Include/ReportConfig.php
for reference.  Most of the configuration options that were previously set
in these files have been moved into the database.  You can now set these 
options by selecting Admin->Edit General Settings and 
Admin->Edit Report Settings.
