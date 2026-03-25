#!/bin/bash

# ==============================================================================
# QR Coupon SaaS - Automated Installer for Ubuntu 22.04 / 24.04
# ==============================================================================
# This script installs Apache, PHP 8.x, MySQL, and configures the environment.
# Must be run as root or with sudo.
# ==============================================================================

set -e

# --- 1. Pre-flight Checks ---
if [ "$EUID" -ne 0 ]; then
  echo -e "\e[31m[ERROR]\e[0m Please run this script with sudo or as root."
  exit 1
fi

echo -e "\e[32m[INFO]\e[0m Starting QR Coupon SaaS Installation..."
echo -e "\e[32m[INFO]\e[0m Updating system packages..."
apt-get update -y && apt-get upgrade -y

# --- 2. Install Dependencies ---
echo -e "\e[32m[INFO]\e[0m Installing Apache, PHP, MySQL, and extensions..."
apt-get install -y apache2 mysql-server php libapache2-mod-php \
    php-mysql php-cli php-curl php-json php-mbstring php-xml php-zip \
    unzip git curl

# --- 3. Configure Apache ---
echo -e "\e[32m[INFO]\e[0m Configuring Apache web server..."
# Enable mod_rewrite for .htaccess rules
a2enmod rewrite

# Update Apache default configuration to AllowOverride All for the web root
cat > /etc/apache2/sites-available/000-default.conf << 'APACHE_CONF'
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
APACHE_CONF

systemctl restart apache2

# --- 4. Configure Database ---
echo -e "\e[32m[INFO]\e[0m Configuring MySQL Database..."
DB_NAME="qr_coupons"
DB_USER="qr_admin"
DB_PASS=$(openssl rand -base64 12)

# Run MySQL queries securely
mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# --- 5. Environment Setup ---
echo -e "\e[32m[INFO]\e[0m Setting up project environment variables..."

# Copy current directory contents to /var/www/html if not already there
CURRENT_DIR=$(pwd)
if [ "$CURRENT_DIR" != "/var/www/html" ]; then
    echo -e "\e[32m[INFO]\e[0m Copying project files to /var/www/html..."
    cp -r ./* /var/www/html/
    cp .htaccess /var/www/html/ 2>/dev/null || true
    cd /var/www/html
fi

# Generate strong secrets
JWT_SECRET=$(openssl rand -base64 32)
CRON_SECRET=$(openssl rand -base64 16)

# Create .env file
cat > .env << ENV_FILE
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

DB_HOST=127.0.0.1
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
DB_PORT=3306

JWT_SECRET=${JWT_SECRET}
CRON_SECRET=${CRON_SECRET}

COUPON_RESET_HOURS=24
ENV_FILE

# Set permissions
echo -e "\e[32m[INFO]\e[0m Setting file permissions..."
chown -R www-data:www-data /var/www/html
find /var/www/html -type d -exec chmod 755 {} \;
find /var/www/html -type f -exec chmod 644 {} \;
chmod 600 /var/www/html/.env

# --- 6. Final Instructions ---
echo -e "\e[32m[SUCCESS]\e[0m Installation complete!"
echo ""
echo "Database configuration:"
echo "-----------------------"
echo "Host:     127.0.0.1"
echo "Database: ${DB_NAME}"
echo "User:     ${DB_USER}"
echo "Password: ${DB_PASS}"
echo ""
echo -e "\e[33m[ACTION REQUIRED]\e[0m"
echo "Please navigate to http://<your-server-ip>/install.php to seed the database and create the admin account."
echo "After installation, delete setup.sh and install.php for security."

exit 0