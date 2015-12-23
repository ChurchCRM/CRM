# Manual Installation

Currently as ChurchCRM is still in development an install script is not yet available. ChurchCRM can run on a LAMP server (Linux, Apache, Mysql, PHP) or a Shared Hosting account through a manual installation.

## How Do I Install ChurchInfo?
--------------------
1. The .tar.gz file download contains a directory called "churchinfo"
Extract the file and place the contents into a directory in the document root of your Web server or via FTP to a shared hosting account

2. Within the directory you'll find a directory called "SQL"
containing a file named "Install.sql". Contained in this file are
the SQL statements necessary to create the ChurchInfo database.

 - For LAMP servers, log onto your database server under the root account (or other account
allowed to create databases), create a database for ChurchCRM, and
then run the contents of Install.sql to create the tables and initial
data.

For example:

mysqladmin -u [user] -p create [database-name]
mysql -u [user] -p [database-name] < Install.sql

 - For shared hosting accounts you must create a database within your hosting control panel, and import the Install.sql script $


3) Within the folder, you'll find a directory called "Include"
containing a file named "Config.php". The first statements in this
file are the database connection parameters:

For example:

$sSERVERNAME = "localhost";

$sUSER = "root";

$sPASSWORD = "password";

$sDATABASE = "churchinfo";

Change these parameters to match the mysql server and user account you
intend to use.  You MUST set the $sRootPath option properly as described 
in Config.php.  It is NOT recommended that you use the ROOT account for
accessing your database.


4) You should be able to access ChurchCRM at "http://[server
name]/churchinfo". The database script will have set up
an initial user called "Admin" with a password of
"churchinfoadmin" (passwords are case insensitive). You will be prompted
to change this password upon login.  Once you have created other user
accounts, you may delete or rename this default account.  Just make
sure that you always have a user with administrative privledges.

5.) Select Admin->Edit General Settings and Admin->Edit Report Settings
to finish customizing your installation.

- You may need to change the default TrueType font path for the included
JPGraph library used for the daily donation report.  To do this, you must
edit line 38 of the file Include/jpgraph-1.13/src/jpgraph.php.  If you
have JPGraph and FPDF elsewhere on your server, you can specify where in
the general settings page.

Security Considerations:
---------------------
- If you are using the database backup utility, you need to make sure
that the churchinfo/SQL directory is not accessible to your users!
Otherwise, with the right timing, anybody can download the temporary
files used in creating database backups and thus read the entire contents
of the database!  Different web servers have different means of access
control.  In Apache, for example, you might add a section something
like this to your httpd.conf:

<Directory /home/httpd/html/churchinfo/SQL>
 Order deny,allow
 Deny from all
</Directory>

Please see your web server's documentation if you need further help.
