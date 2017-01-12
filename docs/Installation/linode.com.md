

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


