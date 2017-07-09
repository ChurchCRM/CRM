#!/bin/bash

cd /vagrant/src
find . -iname '*.php' | sort | grep -v ./vendor | xargs xgettext --from-code=UTF-8 -o /vagrant/locale/messages.po -L PHP
cd /vagrant/locale/
php extract-db-locale-terms.php
cd db-strings
find . -iname "*.php" | sort | xargs xgettext --join-existing --from-code=UTF-8 -o /vagrant/locale/messages.po
cd ..
rm db-strings/*
i18next-extract-gettext --files='/vagrant/src/skin/js/*.js' --output=/vagrant/locale/js-strings.po
tail -n +10 /vagrant/locale/js-strings.po >> /vagrant/locale/messages.po
#rm /vagrant/locale/js-strings.po