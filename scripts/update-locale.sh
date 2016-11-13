#!/bin/bash

cd /vagrant/src
find . -iname '*.php' | grep -v ./vendor | xargs xgettext --from-code=UTF-8 -o locale/messages.po -L PHP
cd /vagrant/scripts/
php extract-db-locale-terms.php
xgettext --join-existing --from-code=UTF8 -o /vagrant/src/locale/messages.po strings.php
rm strings.php