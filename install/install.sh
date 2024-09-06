#!/usr/bin/env sh

# Error on unset variable or parameter and exit
set -u

DATABASE_NAME="$1"
DATABASE_USERNAME="$2"
DATABASE_PASSWORD="$3"

# Determine if the script is run as root
if [ "$(id -u)" -eq 0 ]; then
    SUDO=""
else
    SUDO="sudo"
fi

# Function to check if the OS is Ubuntu
check_os() {
    # Check if /etc/os-release exists
    if [ -f /etc/os-release ]; then
        # Extract the ID value from /etc/os-release
        os_id=$(grep '^ID=' /etc/os-release | cut -d= -f2 | tr -d '"')

        # Print a warning to stderr if the OS is not Ubuntu
        if [ "$os_id" != "ubuntu" ]; then
            echo "Warning: This script isn't regularly tested on non-Ubuntu installations." >&2
        fi
    else
        echo "Error: /etc/os-release file not found. Cannot determine the OS." >&2
    fi
}

check_os

# Function to run a command and exit if it fails
run_or_exit() {
    "$@"
    status=$?
    if [ $status -ne 0 ]; then
        echo "Command failed: $*" >&2
        exit 1
    fi
}

# Function to download files using curl or wget
download_file() {
    URL="$1"
    OUTPUT="$2"

    if command -v curl >/dev/null 2>&1; then
        run_or_exit curl -L -o "$OUTPUT" "$URL"
    elif command -v wget >/dev/null 2>&1; then
        run_or_exit wget -O "$OUTPUT" "$URL"
    else
        echo "Error: Neither curl nor wget is available." >&2
        exit 1
    fi
}

# Package installation function based on available package managers
if command -v apt-get >/dev/null 2>&1; then
    install_packages() {
        run_or_exit $SUDO apt-get update
        run_or_exit $SUDO apt-get install -y "$@"
    }
elif command -v dnf >/dev/null 2>&1; then
    install_packages() {
        run_or_exit $SUDO dnf install -y "$@"
    }
elif command -v yum >/dev/null 2>&1; then
    install_packages() {
        run_or_exit $SUDO yum install -y "$@"
    }
else
    echo "Error: No supported package manager found." >&2
    exit 1
fi

# Service management function based on available service managers
if command -v systemctl >/dev/null 2>&1; then
    enable_and_start_service() {
        run_or_exit $SUDO systemctl enable "$1"
        run_or_exit $SUDO systemctl start "$1"
    }

    restart_service() {
        run_or_exit $SUDO systemctl restart "$1"
    }
else
    echo "Error: No supported service manager found." >&2
    exit 1
fi

# Install required packages
install_packages apache2 curl gawk libapache2-mod-php mariadb-client mariadb-server \
  php php-bcmath php-cli php-curl php-dev php-gd php-intl php-mbstring php-mysql \
  php-soap php-xml php-zip unzip

# Common logic for all distributions
cd /tmp

# Get the latest version of ChurchCRM
if command -v curl >/dev/null 2>&1; then
    VERSION_CMD="curl -Is https://github.com/ChurchCRM/CRM/releases/latest | awk -F\/ '/^location:/ {sub(/\r$/, \"\", \$NF); print \$NF}'"
elif command -v wget >/dev/null 2>&1; then
    VERSION_CMD="wget --spider --server-response https://github.com/ChurchCRM/CRM/releases/latest 2>&1 | awk -F\/ '/^  Location:/ {sub(/\r$/, \"\", \$NF); print \$NF}'"
else
    echo "Error: Neither curl nor wget is available." >&2
    exit 1
fi

VERSION=$(eval "$VERSION_CMD")
DOWNLOAD_URL="https://github.com/ChurchCRM/CRM/releases/download/$VERSION/ChurchCRM-$VERSION.zip"
download_file "$DOWNLOAD_URL" "ChurchCRM-$VERSION.zip"
run_or_exit unzip "ChurchCRM-$VERSION.zip" && rm "ChurchCRM-$VERSION.zip"
run_or_exit $SUDO chown -R www-data:www-data churchcrm
run_or_exit $SUDO mv churchcrm /var/www/html/

enable_and_start_service apache2
enable_and_start_service mariadb

## Creating the database
run_or_exit $SUDO mariadb -uroot -p -e "CREATE DATABASE ${DATABASE_NAME} /*\!40100 DEFAULT CHARACTER SET utf8 */;
CREATE USER ${DATABASE_USERNAME}@'localhost' IDENTIFIED BY '${DATABASE_PASSWORD}';
GRANT ALL ON ${DATABASE_NAME}.* TO '${DATABASE_USERNAME}'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;"

echo "Please make sure to secure your database server:"
echo " $SUDO mysql_secure_installation"

PHP_CONF_D_PATH="/etc/php/conf.d/churchcrm.ini"
PHP_VERSION=$(php -r 'echo phpversion();' | cut -d '.' -f 1,2)

if [ "$PHP_VERSION" = "8.3" ]; then
  PHP_CONF_D_PATH="/etc/php/8.3/apache2/conf.d/99-churchcrm.ini"
fi

# Set-up the required PHP configuration
run_or_exit $SUDO tee "$PHP_CONF_D_PATH" << 'TXT'
file_uploads = On
allow_url_fopen = On
short_open_tag = On
memory_limit = 256M
upload_max_filesize = 100M
max_execution_time = 360
TXT

# Set-up the required Apache configuration
run_or_exit $SUDO tee /etc/apache2/sites-available/churchcrm.conf << 'TXT'
<VirtualHost *:80>

ServerAdmin webmaster@localhost
DocumentRoot /var/www/html/churchcrm/
ServerName ChurchCRM

<Directory /var/www/html/churchcrm/>
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>

ErrorLog ${APACHE_LOG_DIR}/error.log
CustomLog ${APACHE_LOG_DIR}/access.log combined

</VirtualHost>
TXT

# Enable apache rewrite module
run_or_exit $SUDO a2enmod rewrite

# Disable the default apache site and enable ChurchCRM
run_or_exit $SUDO a2dissite 000-default.conf
run_or_exit $SUDO a2ensite churchcrm.conf

# Restart apache to load new configuration
restart_service apache2.service
