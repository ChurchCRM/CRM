#!/bin/bash

cd src
# Extract PHP Terms
find . -iname '*.php' | sort | grep -v ./vendor | xargs xgettext --from-code=UTF-8 -o ../locale/messages.po -L PHP

# Extract JS Terms
i18next-extract-gettext --files=skin/js/*.js --output=../locale/js-strings.po

cd ../locale
# merge PHP & JS Terms
msgcat messages.po js-strings.po -o messages.po

# Extract DB Terms
php extract-db-locale-terms.php
cd db-strings
find . -iname "*.php" | sort | xargs xgettext --join-existing --from-code=UTF-8 -o ../messages.po

# Cleanup
cd ..
rm js-strings.po
rm db-strings/*
