#!/usr/bin/env bash

# --watch keep looking for changes
sass --watch /var/www/public/skin/churchcrm.scss:/var/www/public/skin/churchcrm.min.css --style compressed
