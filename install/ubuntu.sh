sudo apt update ; sudo apt upgrade -y 
sudo apt install unzip wget git -y
sudo apt install apache2 -y
sudo apt install mysql-server -y

########### un-comment if you are using php7.4####### 
#sudo apt install software-properties-common -y 
#sudo add-apt-repository ppa:ondrej/php -y
#sudo apt update
#sudo apt install php7.4 libapache2-mod-php7.4 php7.4-mysql php7.4-xml php7.4-zip php7.4-curl php7.4-gd php7.4-mbstring php7.4-cli -y
######################################################

sudo apt install php libapache2-mod-php php-mysql php-xml php-zip php-curl php-gd php-mbstring php-cli -y
cd /tmp
git clone https://github.com/ChurchCRM/CRM.git
cd /var/www
sudo rm -rf html
sudo cp -r /tmp/CRM/src /var/www/htnl
sudo chmod 755 /var/www/html/Include
sudo chmod 755 /var/www/html/Images
sudo systemctl restart apache2 

## Creating the database ##Please change the variables 
## Please make sure to secure your Mysql server 
BIN_MYSQL=$(which mysql)
DB_HOST='localhost'
DB_NAME='' ## Enter the database name 
DB_USER='' ## enter the database username 
DB_PASS= '' ## enter the password 
mysql -e "CREATE DATABASE ${MAINDB} /*\!40100 DEFAULT CHARACTER SET utf8 */;"
mysql -e "CREATE USER ${MAINDB}@localhost IDENTIFIED BY '${PASSWDDB}';"
mysql -e "GRANT ALL PRIVILEGES ON ${MAINDB}.* TO '${MAINDB}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"
