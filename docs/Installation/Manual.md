# Manual Installation

Currently, as ChurchCRM is still in development, an install script is not yet available. ChurchCRM can run on a LAMP server (Linux, Apache, Mysql, PHP) or a Shared Hosting account through a manual installation.

## Check your server

[Upload] (https://github.com/ChurchCRM/Check) `check.php` to your web server's document root, and then visit `check.php` in a web browser.

This page should tell you everything you need to know to determine whether or not you can run ChurchCRM. 

[More at our check repo] (https://github.com/ChurchCRM/Check) 

## How Do I Install ChurchCRM?

1. [Download the latest release] (https://github.com/ChurchCRM/CRM/releases/latest)

2. The downloaded zip file contains a directory called "churchcrm".
Extract the files and place the contents into a directory in the document root of your Web server or via FTP to a shared hosting account

3. Create a Church CRM blank db and user that have full permissions on the db.

 - For shared hosting accounts you must create a database within your hosting control panel

 - For LAMP servers, log onto your database server under the root account (or other account
allowed to create databases), and create a database for ChurchCRM.

   * For example: ```mysqladmin -u [user] -p create [database-name]```

4. You should be able to access ChurchCRM at "http://[servername]/churchcrm". The setup page will help you configure the app for first-time use.  

The database script will have set up an initial user called "**Admin**" with a password of "**changeme**" (passwords are case insensitive). You will be prompted to change this password upon login.  Once you have created other user accounts, you may delete or rename this default account.  Just make sure that you always have a user with administrative priviledges.

## Configuring ChurchCRM

### General Settings
At the top right of the page select the gear icon ⚙ then select >>Edit General Settings

* You might want to change the `sDefaultPass` to something that pertains to your organization. This is the default password that all new accounts are assigned until they log in and set their own.
* Set `sDefaultCity` to the location for your organization
* Set `sDefaultState` (This must be a two letter abbreviation)

##### Email Settings

* Set `sToEmailAddress` to the default email address you want requests to come to (ie. `webmaster@domain.com`)
* Set `sSMTPHost` as the email relay server.
* Set `sSMTPUser` and `SMTPPass` to the credentials for the email account.

##### Other Settings

* Set `sChurchLatitude` (You can find this information at http://www.latlong.net/)
* Set `sChurchLongitude` (You can find this information at http://www.latlong.net/)
* Set `sHeader`. You can add a custom header to ChurchCRM by entering the HTML for the custom header.
    Example: If you enter ``<H2>My Church</H2>``, ChurchCRM will display "My Church" in large, 
    bold letters at the top of each page.
* Set `mailChimpApiKey`. MailChimp is a web service that makes it easy to send and track bulk emails. If you do not have an account, create a free one here: http://mailchimp.com/signup. Once you have an account, create an API Key at http://kb.mailchimp.com/accounts/management/about-api-keys and enter that value into this setting.


### Report Settings
At the top right of the page, select the gear icon ⚙ and then select >>Edit Report Settings.
The settings in this section define various reports, such as giving statements.

##### Church Information

* Set `sChurchName`
* Set `sChurchAddress` 	
* Set `sChurchCity`
* Set `sChurchState`	
* Set `sChurchZip`
* Set `sChurchPhone`
* Set `sChurchEmail`
* Set `sHomeAreaCode`

##### Signature Information
These are the different signatures used on your financial reports.

* Set `sTaxSigner`
* Set `sReminderSigner`
* Set `sConfirmSigner`

##### Letter Head Graphic

* Set `bDirLetterHead`

### Register your copy
At the top right of the page select the gear icon ⚙ and then select >>Update Registration.
This information is used to inform you of updates to the system.

Security Considerations:
---------------------
- If you are using the database backup utility, make sure the churchcrm/SQL directory is not accessible to your users!
Otherwise, with the right timing, anybody can download and read the temporary
files used in creating database backups. Different web servers have various means of access
control.  For example, in Apache you might add this to your httpd.conf:

<Directory /home/httpd/html/churchcrm/SQL>
 Order deny,allow
 Deny from all
</Directory>

Please see your web server's documentation if you need further help.
