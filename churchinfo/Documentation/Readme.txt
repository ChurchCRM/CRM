How do I run ChurchInfo?
-------------------------
Running ChurchInfo is not complicated if you have experience with
Web applications. If you don't, there are a few things to get used to.
It is important to understand is that ChurchInfo is a Web-based
application, which means it has two distinct sides:

The "server", on which the application actually runs. This is a
centrally located computer that stores the files and information that
ChurchInfo needs to run

The "client", through which a user interacts with the application via
a Web browser.

There is only one server, but there can be an unlimited number of
clients.

What software do I need to run ChurchInfo?
-------------------------------------------

A PHP-compatible Web server (Apache is recommended)
The MySQL database server (version 4.0 or higher)
PHP (version 4.1 or higher)

Certain PHP modules (sometimes referred to as extensions) must be enabled:
'mysql' and 'gd' version 2 or higher.  On some platforms, you may need to
install specific packages for these modules.

For Debian GNU/Linux users, you should install these packages:
mysql-server, mysql-common, mysql-client, php4, php4-mysql, php4-gd2,
php4-pear, and whatever Apache packages suit your needs.

ChurchInfo can be run entirely with free software.  In fact, that's
half the point of why it was written!

What type of server do I need?
------------------------------
The computer can be running almost any operating system: 
Windows 9x/2000/XP,Linux, BSD, Solaris, MacOS, etc. so long as 
the OS can support a PHP-compatible Web server (such as Apache), 
and can run a MySQL database server.  We highly recommend Linux 
or FreeBSD but the choice is yours. As for PHP and MySQL, we do
have the following requirements:
PHP   - Version 4.1 or greater
      - GD enabled
      - PEAR enabled
      - gettext enabled
      - register_globals turned OFF (see below)
MySQL - Version 4.0 or greater

What if my host doesn't have register_globals turned OFF?
---------------------------------------------------------
There is a simple work around if your server does not have register_globals
turned off. Create a file called ".htaccess" with a simple text editor and 
insert the following line into that new file:
	php_flag register_globals off
Save this file and upload this file into the main ChurchInfo directory.

What kind of client computers do I need?
----------------------------------------
The interface will work fine with any modern standards-compliant web
browser.  However, be warned:  Microsoft Internet Explorer is in many
cases NOT compliant to well-established W3C-consortium Internet standards.
If you have troubles, please use a quality (and free) browser such as
Mozilla or "Netscape 7" instead.  Alternatively, there is a setting in
Include/Config.php that can disable certain interface features for
non-compliant browsers.

What if I only have one computer?
---------------------------------
That's fine, so long as the computer satisfies the requirements for
both the server and client. Both sides of ChurchInfo can be on the
same computer.

Where do I get a Web server?
----------------------------
The free Apache web server will work on Windows, Linux or about any
flavor of Unix.  A few extra steps may be involved to configure Apache's
PHP module.  Most distributions of Linux are ready for ChurchInfo nearly
"out of the box" or at worst with the easy installation of a couple
relevant Apache and PHP packages.

Where do I get a MySQL database and Apache web server?
------------------------------------------------------
MySQL is available from www.mysql.com
Apache is available from www.apache.org
PHP is available from www.php.net


How Do I Install ChurchInfo?
--------------------
1) The .tar.gz file download contains a directory called "churchinfo"
Place this directory in the document root of your Web server.

2) Within the directory, you'll find a directory called "SQL"
containing a file named "Install.sql". Contained in this file are
the SQL statements necessary to create the ChurchInfo database. Log
onto your database server under the root account (or other account
allowed to create databases), create a database for ChurchInfo, and
then run the contents of Install.sql to create the tables and initial
data.

For example:

mysqladmin -u [user] -p create [database-name]
mysql -u [user] -p [database-name] < Install.sql


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
in Config.php.

4) You should be able to access ChurchInfo at "http://[server
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
