#!/usr/bin/env bash
githubToken=$1

if [ -z ${githubToken} ]; then
    echo -n "Enter your github token and press [ENTER]: "
    read githubToken
fi

sudo npm install github-release-notes@0.6.3 -g

gren --username=ChurchCRM --repo=CRM --action=changelog --override=true --time-wrap=history --ignore-issues-with=wontfix,duplicate,norepro,question --token=${githubToken}

