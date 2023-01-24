<p align="center">
    <img src="https://docs.casperlabs.io/icon/Casper_Wordmark_Red_RGB.png" width="400">
</p>

Casper Association Portal API

This is the backend API repo of the portal. To see the frontend webapp repo, visit https://github.com/ledgerleapllc/CasperAssociationPortal

## Prerequisites

 - Apache
 - Mysql (or an RDS server)
 - PHP 8.1
 - Composer 2.5+
 - Casper Client CLI utility

Testing done on AWS EC2 medium instance running Ubuntu 20.

Always a good idea to update fresh servers.

```bash
sudo apt-get update
```

### Apache
```bash
sudo apt -y install apache2
sudo a2enmod rewrite
sudo a2enmod headers
sudo a2enmod ssl
sudo apt -y install software-properties-common
```

### Mysql

Note: If you are using an RDS, or some kind of redundant database setup, this part can be skipped. The only thing needed is the host, port, and credentials of your DB.

```bash
sudo apt -y install mysql-server
sudo mysql
mysql> ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '$DATABASE_PASSWORD';
mysql> exit
sudo service mysql restart
```

### PHP
```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt-get update
sudo apt-get install -y php8.1
sudo apt-get install -y php8.1-{bcmath,bz2,intl,gd,mbstring,mysql,sqlite3,zip,common,curl,xml,gmp}
```

### Composer
```bash
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer
```

### Install Casper Client
```bash
echo "deb https://repo.casperlabs.io/releases" bionic main | sudo tee -a /etc/apt/sources.list.d/casper.list
curl -O https://repo.casperlabs.io/casper-repo-pubkey.asc
sudo apt-key add casper-repo-pubkey.asc
sudo apt update
sudo apt install casper-client
```

## Setup vhosts

**Note:** Using example drectory **/var/www/CasperAssociationPortalBackend** to point Apache servers.

Updated backend vhost
```
<VirtualHost *:80>
    ServerName members-backend.casper.network
    DocumentRoot /var/www/CasperAssociationPortalBackend/public

    ErrorDocument 404 /404.php
    ErrorDocument 403 /403.php
    ErrorDocument 500 /500.php

    <Directory /var/www/CasperAssociationPortalBackend/public>
        Options -MultiViews
        AllowOverride All
        Require all granted

        Header set Access-Control-Allow-Origin "*"
        Header set Access-Control-Allow-Headers "*"
        Header set Access-Control-Allow-Methods "*"
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

## Build
```bash
composer install
composer update
sudo chown -R www-data:www-data public/documents/
```

## Install cronjob

Single job added to your crontab manages all the others in the system. The cron script is located in **crontab** directory. Example absolute path might look like **/var/www/CasperAssociationPortalBackend/crontab/cron.php**.

```bash
crontab -e
```

Then add:

```
* * * * * php /path/to/cloned/repo/crontab/cron.php
```

## Test

There are unit tests, and integration tests built in, maintained to cover at least 80% of the entire codebase.

To run unit tests:
```bash
composer run-script test-unit
```

To run integration tests:
```bash
composer run-script test-integration
```

To run all tests:
```bash
composer run-script test
```

Generate documention using Phpdoc with:
```bash
composer run-script generate-docs
```

Will create the docs/ directory with static documentation html, which can then be mapped to a vhost for public availability.
