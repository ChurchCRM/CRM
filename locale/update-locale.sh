#!/bin/bash

cd src
# Extract PHP Terms
find . -iname '*.php' | sort | grep -v ./vendor | xargs xgettext --no-location --no-wrap --from-code=UTF-8 -o ../locale/messages.po -L PHP

cd ../locale 

# Extract DB Terms
php extract-db-locale-terms.php
cd db-strings
find . -iname "*.php" | sort | xargs xgettext --no-location --no-wrap --join-existing --from-code=UTF-8 -o ../messages.po

cd ../../
# Extract JS & React Terms
i18next -c locale/i18next-parser.config.js
i18next-conv -l en -s locale/locales/en/translation.json -t locale/locales/en/translation.po

# merge PHP & DB & JS Terms
msgcat locale/messages.po locale/locales/en/translation.po -o locale/messages.po

# Cleanup
rm locale/locales/en/translation.*
rm locale/db-strings/*
