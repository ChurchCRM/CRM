#!/bin/bash

cd src
# Extract PHP Terms
find . -iname '*.php' | sort | grep -v ./vendor | xargs xgettext --no-location --no-wrap --from-code=UTF-8 -o ../locale/messages.po -L PHP

# Extract JS Terms
i18next-extract-gettext --files=skin/js/*.js --output=../locale/js-strings.po

cd ../locale

# Extract DB Terms
php extract-db-locale-terms.php
cd db-strings
find . -iname "*.php" | sort | xargs xgettext --no-location --no-wrap --join-existing --from-code=UTF-8 -o ../messages.po


# merge PHP & DB & JS Terms
cd ..
msgcat messages.po js-strings.po -o messages.po

# Cleanup
rm js-strings.po
rm db-strings/*
