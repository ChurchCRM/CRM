#!/bin/bash

cd /vagrant/src
find . -iname '*.php' | grep -v ./vendor | xargs xgettext --from-code=UTF-8 -o locale/messages.po -L PHP
cd /vagrant/scripts/
php extract-db-locale-terms.php
cd db-strings
find . -iname "*.php" | xargs xgettext --join-existing --from-code=UTF-8 -o /vagrant/src/locale/messages.po 
cd ..
rm db-strings/*