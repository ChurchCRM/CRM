

#System Update

`sudo yum update –y`

#http Install

`sudo yum install httpd –y`

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
`sudo yum install php php-pear php-mccrypt php-mysql php-zip php-phar php-gd php-mbstring -y`

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

`service httpd restart`
