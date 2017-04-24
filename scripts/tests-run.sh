cd tests/
php security-checker.phar security:check /vagrant/src/composer.lock
php security-checker.phar security:check /vagrant/tests/composer.lock
cd behat/
../vendor/bin/behat