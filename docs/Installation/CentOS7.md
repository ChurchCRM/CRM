

#System Update

```
sudo yum update -y
sudo yum install unzip -Y
```

#http Install

`sudo yum install httpd -y`

`vi /etc/httpd/conf.d/churchcrm.conf`

```
NameVirtualHost *:80
        
<VirtualHost *:80>
    ServerAdmin webmaster@example.com
    ServerName crm.churchcrm.io
    #ServerAlias www.example.com
    DocumentRoot /var/www/html/churchcrm/public_html/
    ErrorLog /var/www/html/churchcrm/logs/error.log
    CustomLog /var/www/html/churchcrm/logs/access.log combined
</VirtualHost>
```

`sudo mkdir -p /var/www/html/churchcrm/{public_html,logs}`

`chkconfig --level 234 httpd on`

#Php Install
`sudo yum install php70w php70w-pear php70w-mcrypt php70w-mysql php70w-zip php70w-phar php70w-gd php70w-mbstring -y`

`vi /etc/php.ini`

```
error_reporting = E_COMPILE_ERROR|E_RECOVERABLE_ERROR|E_ERROR|E_CORE_ERROR
error_log = /var/log/php/error.log
max_input_time = 30
```
```
sudo mkdir /var/log/php
sudo chown apache /var/log/php
```

`vi /etc/httpd/conf/httpd.conf`

find <Directory "/var/www/html">

update AllowOverride to All

`service httpd restart`

#MySQL
```
wget http://repo.mysql.com/mysql-community-release-el7-5.noarch.rpm
sudo rpm -ivh mysql-community-release-el7-5.noarch.rpm
sudo yum update -y
sudo yum install mysql-server -y
chkconfig --level 234 mysqld on
service mysqld start
sudo mysql_secure_installation
```

Create the DB and the user... 

#Install CRM 

```
cd /var/www/
rm -rf html
wget https://github.com/ChurchCRM/CRM/releases/download/2.4.4/ChurchCRM-2.4.4.zip
unzip ChurchCRM-2.4.4.zip
```

