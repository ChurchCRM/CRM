#!/usr/bin/env bash
currentBranch=`git rev-parse --abbrev-ref HEAD`
githubToken=$1

if [ -z ${githubToken} ]; then
    echo -n "Enter your github token and press [ENTER]: "
    read githubToken
fi

echo "**************************************"
echo "Generating Change-Log for  Branch: $currentBranch"
echo "**************************************"
gren --username=ChurchCRM --repo=CRM --token=${githubToken} --action=changelog --override=true --time-wrap=history
echo "**************************************"

