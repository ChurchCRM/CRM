sudo apt update ; sudo apt upgrade -y 
sudo apt install upzip wget -y
sudo apt install apache2 -y
sudo apt install mysql-server -y
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo yum install php7.4 libapache2-mod-php7.4 php7.4-mysql php7.4-xml php7.4-zip php7.4-curl php7.4-gd php7.4-mbstring -y

cd /var/www/
sudo rm -rf html
sudo wget https://github.com/ChurchCRM/CRM/releases/download/4.5.4/ChurchCRM-4.5.4.zip
sudo unzip ChurchCRM-4.5.4.zip
sudo mv churchcrm/ html
sudo chmod 755 /var/www/html/Include
sudo chmod 755 /var/www/html/Images

