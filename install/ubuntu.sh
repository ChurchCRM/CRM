sudo apt update ; sudo apt upgrade -y 
sudo apt install upzip wget -y
sudo apt install apache2 -y
sudo apt install mysql-server -y
sudo apt install php libapache2-mod-php php-mysql -y
cd /var/www/
rm -rf html
wget https://github.com/ChurchCRM/CRM/releases/download/4.5.4/ChurchCRM-4.5.4.zip
unzip ChurchCRM-4.5.4.zip
mv churchcrm/ html

