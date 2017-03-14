#!/usr/bin/env bash
githubToken=$1

if [ -z ${githubToken} ]; then
    echo -n "Enter your github token and press [ENTER]: "
    read githubToken
fi

gren --username=ChurchCRM --ignore-issues-with=wontfix,duplicate,norepro --repo=CRM --action=changelog --override=true --time-wrap=history --token=${githubToken}

