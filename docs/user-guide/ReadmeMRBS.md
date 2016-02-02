# _MRBS_ integration

This version of ChurchInfo includes a portal feature which allows MRBS run
within ChurchInfo.  Here are the installation steps required to enable this
feature.

1. Download the desired version of MRBS from https://sourceforge.net/projects/mrbs/?source=directory
>**Note:** The current version that has been tested with ChurchInfo is 1.4.11

2. Copy the MRBS distribution to your server.  The default value for the MRBS location is `/mrbs-1.4.11`, next to `/churchinfo`.  If you use a different location the global setting `sMRBSPath` must be modified to reflect the actual location.

3. Edit the MRBS configuration file `web/config.inc.php` specify the same mysql database that is used by ChurchInfo.  The MRBS system will install its tables with the prefix `mrbs_` so they do not conflict with ChurchInfo.

4. Note that you must specify a valid time zone e.g. `$timezone = "America/New_York";`

5. Add this line in config.inc.php to set the authentication scheme to use the mysql database: `$auth["type"] = "db";`

6. Install the MRBS tables by importing this SQL file from the MRBS distribution into the ChurchInfo database: `tables.my.sql`

7. Update the ChurchInfo database to enable the MRBS option by importing this SQL file: `churchinfo/mysql/install/AddMRBS.sql`
>**Note:** This scripts adds one menu option and two general parameters that can be modified later using the menu configuration pages and the general settings pages respectively.

8. Edit the file that sets up the session in the MRBS distribution.
  - In version 1.4.8 of MRBS it is this file: `web/session_php.inc`

  - In version 1.4.11 of MRBS it is this file: `session/session_php.inc`

  Comment-out the line which sets the session name (located at line 50 in version 1.4.8 of MRBS). It should look like this:

  ```
  //session_name("MRBS_SESSID");  // call before session_set_cookie_params() - see PHP manual
  ```

  This change allows MRBS to use the same PHP session as ChurchInfo

9. Now log into ChurchInfo and look in the Main menu for the MRBS option at the bottom.  This  menu option will run MRBS under ChurchInfo, passing information about the current user down to MRBS.  The MRBS user will be an administrator if the current user has admin privileges in ChurchInfo.  The MRBS user will be able to add bookings if if the current user has permission to add records.  

10. Note that the MRBS system may be run outside of ChurchInfo by browsing to its web directory. For example, if ChurchInfo is located in the standard director the URL will be

  - http://www.domain.com/churchinfo

  and the MRBS system will be at

  - http://www.domain.com/mrbs-1.4.8/web
