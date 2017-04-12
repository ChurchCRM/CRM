#!/usr/bin/env bash
githubToken=$1

if [ -z ${githubToken} ]; then
    echo -n "Enter your github token and press [ENTER]: "
    read githubToken
fi

sudo npm install github-release-notes@0.8.0 -g

gren --action=changelog --generate --tags=all --username=ChurchCRM --repo=CRM --time-wrap=history --data-source=issues --ignore-issues-with=wontfix,duplicate,norepro,question --token=${githubToken}

