
## Overview

[ChurchCRM](http://churchcrm.io) is is based on [ChurchInfo](http://churchdb.org) which was based on InfoCentral.

The software was developed by a team of volunteers, in their spare time, for the purpose of providing churches and with high-quality free software.

If you'd like to find out more or want to help out, checkout our [github.com repo](https://github.com/ChurchCRM/CRM/)

---

**ChurchCRM is currently still in development.**

We're progressing quickly, but the documentation still needs filling in, and there are a few rough edges.  The 1.0 release is planned to arrive in the next few months.

---

#### Host anywhere.

TODO

#### Great themes available.

---

## Installation

ChurchCRM is a PHP/MySQL application which runs on a web server, providing web pages so users can update and access the data in the database. You can run both the server and the browser on a single computer, but the real power of a web database application is visible when you have multiple users accessing the database from their own computers.

---
##How do I run ChurchCRM?

Running ChurchCRM is not complicated if you have experience with
Web applications. If you don't, there are a few things to get used to.
It is important to understand is that ChurchCRM is a Web-based
application, which means it has two distinct sides:

The "server", on which the application actually runs. This is a
centrally located computer that stores the files and information that
ChurchInfo needs to run

The "client", through which a user interacts with the application via
a Web browser.

There is only one server, but there can be an unlimited number of
clients.

#ChurchCRM Requirements?
---
A PHP-compatible Web server (Apache is recommended)
The MySQL database server (version 4.0 or higher)
PHP (version 4.1 or higher)

Certain PHP modules (sometimes referred to as extensions) must be enabled:
'mysql' and 'gd' version 2 or higher.  On some platforms, you may need to
install specific packages for these modules.

For Debian GNU/Linux users, you should install these packages:
httpd, mysql, mysql-server, php, php-gd, php-mcrypt, php-mysql, 
php-pear, and whatever Apache packages suit your needs.

ChurchCRM can be run entirely with free software.  In fact, that's
half the point of why it was written!

#What type of server do I need?
---
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

#What if my host doesn't have register_globals turned OFF?
---
There is a simple work around if your server does not have register_globals
turned off. Create a file called ".htaccess" with a simple text editor and 
insert the following line into that new file:
	php_flag register_globals off
Save this file and upload this file into the main ChurchInfo directory.

#What kind of client computers do I need?
---
The interface will work fine with any modern standards-compliant web
browser.  However, be warned:  Microsoft Internet Explorer is in many
cases NOT compliant to well-established W3C-consortium Internet standards.
If you have troubles, please use a quality (and free) browser such as
Mozilla or Chrome instead.  Alternatively, there is a setting in
Admin -> Edit General Settings that can disable certain interface 
features for non-compliant browsers.

#What if I only have one computer?
---
That's fine, so long as the computer satisfies the requirements for
both the server and client. Both sides of ChurchCRM can be on the
same computer.

#Where do I get a Web server?
---
The free Apache web server will work on Windows, Linux or about any
flavor of Unix.  A few extra steps may be involved to configure Apache's
PHP module.  Most distributions of Linux are ready for ChurchCRM nearly
"out of the box" or at worst with the easy installation of a couple
relevant Apache and PHP packages.

Where do I get a MySQL database and Apache web server?

MySQL is available from www.mysql.com
Apache is available from www.apache.org
PHP is available from www.php.net


## Getting started

The application is based on the concepts of people who are members of families and are also members of common interest groups.

After you have installed the ChurchCRM application and can login, you are ready to configure the application.

The first thing to do is enter your church name, address, phone and email address into the Report Settings.

You can add a custom header to ChurchCRM by entering the HTML for the custom header in the General Settings. From the Admin menu, choose “Edit General Settings”. Near the bottom of the General Settings page, enter the HTML for the custom header into the field “sHeader”. Example: If you enter-
```html
<H2>My Church</H2>
```

ChurchCRM will display “My Church” in large, bold letters at the top of each page.

During the configuration stage, give some consideration to how you will use ChurchCRM:

1. What are the groups that you will use?
2. What properties do you need to record for people, families and groups?
3. Do you need to use custom fields?
4. Who needs access to the administration features?
5. Who should be given access to the Financial records?
6. Who can add or change records?

## Deploying

The documentation site that we've just built only uses static files so you'll be able to host it from pretty much anywhere. [GitHub project pages] and [Amazon S3] are good hosting options. Upload the contents of the entire `site` directory to wherever you're hosting your website from and you're done.


## Getting help

To get help with ChurchCRM, please use the [GitHub issues].

[GitHub issues]: https://github.com/ChurchCRM/CRM/issues

