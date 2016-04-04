How do I run ChurchCRM?
-------------------------
Running ChurchCRM is not complicated if you have experience with
Web applications. If you don't, there are a few things to get used to.
It is important to understand is that ChurchCRM is a Web-based
application, which means it has two distinct sides:

The "server", on which the application actually runs. This is a
centrally located computer that stores the files and information that
ChurchCRM needs to run

The "client", through which a user interacts with the application via
a Web browser.

There is only one server, but there can be an unlimited number of
clients.

What software do I need to run ChurchCRM?
-------------------------------------------

A PHP-compatible Web server (Apache is recommended)
The MySQL database server (version 4.0 or higher)
PHP (version 5.4 or higher)

Certain PHP modules (sometimes referred to as extensions) must be enabled:
'mysql' and 'gd' version 2 or higher.  On some platforms, you may need to
install specific packages for these modules.

For Debian GNU/Linux users, you should install these packages:
httpd, mysql, mysql-server, php, php-gd, php-mcrypt, php-mysql, 
php-pear, and whatever Apache packages suit your needs.

ChurchCRM can be run entirely with free software.  In fact, that's
half the point of why it was written!

What type of server do I need?
------------------------------
The computer can be running almost any operating system: 
Windows 9x/2000/XP,Linux, BSD, Solaris, MacOS, etc. so long as 
the OS can support a PHP-compatible Web server (such as Apache), 
and can run a MySQL database server.  We highly recommend Linux 
or FreeBSD but the choice is yours. As for PHP and MySQL, we do
have the following requirements:
PHP   - Version 5.4 or greater
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
Save this file and upload this file into the main ChurchCRM directory.

What kind of client computers do I need?
----------------------------------------
The interface will work fine with any modern standards-compliant web
browser.  However, be warned:  Microsoft Internet Explorer is in many
cases NOT compliant to well-established W3C-consortium Internet standards.
If you have troubles, please use a quality (and free) browser such as
Mozilla or Chrome instead. 

What if I only have one computer?
---------------------------------
That's fine, so long as the computer satisfies the requirements for
both the server and client. Both sides of ChurchCRM can be on the
same computer.

Where do I get a Web server?
----------------------------
The free Apache web server will work on Windows, Linux or about any
flavor of Unix.  A few extra steps may be involved to configure Apache's
PHP module.  Most distributions of Linux are ready for ChurchCRM nearly
"out of the box" or at worst with the easy installation of a couple
relevant Apache and PHP packages.

Where do I get a MySQL database and Apache web server?
------------------------------------------------------
MySQL is available from www.mysql.com
Apache is available from www.apache.org
PHP is available from www.php.net