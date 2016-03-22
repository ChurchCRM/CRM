# Develop using a Vagrant Box

## Get Started

Thanks to Scotch box https://github.com/scotch-io/scotch-box a the LAMP base box.

https://github.com/scotch-io/scotch-box#get-started


## Steps  

Just clone and run Vagrant up

1. `git clone https://github.com/ChurchCRM/CRM.git`

2. `vagrant up`

## Server

Access the Project at http://192.168.33.10/


### Login Info

User: `Admin`
Password: `changeme`

## Database

See https://github.com/scotch-io/scotch-box#database-access for connection info

### CRM DB info

DB: `churchcrm`
DB User: `churchcrm`
DB Password: `churchcrm`

### Vagrant Database Access 
- You can use a MySQL development platform, or your favorite IDE to access the ChurchCRM databases.
- As part of the vagrant development environment, MySQL is configured to listen on all interfaces, and the churchcrm user is allowed to login from any host.
#### NetBeans 8.1 PHP Database Connector Setup
1. Navigate to the "Services" Node from the "Window" menu
2. Right Click "Databases, " and select "New Connection"
3. Choose the "MySQL" Connector.
4. Enter "192.168.33.10" as the Database Host.
5. Enter churchcrm for Database Name, Useranme, and Password.  Your connection string should be as follows:
> jdbc:mysql://192.168.33.10:3306/churchcrm?zeroDateTimeBehavior=convertToNull
6. Click "Test Connection"
7. You should now be able to browse the database schema, and execute queries from the NetBeans IDE.