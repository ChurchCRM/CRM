# Installation Overview & Requirements

## Installation Demo Video

[![ChurchCRM Installation Demo Video](http://img.youtube.com/vi/SMjZpo3aO5Q/0.jpg)](http://www.youtube.com/watch?v=SMjZpo3aO5Q "ChurchCRM Installation Demo Video")

## ChurchCRM Requirements?

ChurchCRM requires a PHP-compatible Web server (such as Apache), 
and can run a MySQL database server.  We highly recommend Linux, but the choice is yours. 
As for PHP and MySQL, we do
have the following requirements:

1. PHP   
      * Version 7.0 or greater
      * GD enabled
      * PEAR enabled
      * gettext enabled
      * register_globals turned OFF (see below)
      * Phar extension must be enabled.

2. MySQL 
      * Version 5.5 or greater

Certain PHP modules (sometimes referred to as extensions) must be enabled:
'mysql' and 'gd' version 2 or higher.  On some platforms, you may need to
install specific packages for these modules.

For Debian GNU/Linux users, you should install these packages:
+ httpd
+ mysql-server
+ php
+ php-gd
+ php-mcrypt
+ php-mysql
+ php-mbstring
+ php-pear
+ php-phar

ChurchCRM can be run entirely with free software.  In fact, that's
half the point of why it was written!

## What if my host doesn't have register_globals turned OFF?

There is a simple work around if your server does not have register_globals
turned off. Create a file called ".htaccess" with a simple text editor and 
insert the following line into that new file:
	php_flag register_globals off
Save this file and upload this file into the main ChurchCRM directory.

## How can I enable the Phar extension?

Some web hosts allow you to selectively enable PHP extensions by the use of a [phprc](http://php.net/manual/en/configuration.php) file.
If your web host does not enable Phar by default, and allows the use of phprc files,
you can add the following to your phprc file:
```
extension=phar.so
```


## Where do I get a Web server?

The free Apache web server will work on Windows, Linux or about any
flavor of Unix.  A few extra steps may be involved to configure Apache's
PHP module.  Most distributions of Linux are ready for ChurchCRM nearly
"out of the box" or at worst with the easy installation of a couple
relevant Apache and PHP packages.

Where do I get a MySQL database and Apache web server?

MySQL is available from www.mysql.com
Apache is available from www.apache.org
PHP is available from www.php.net
