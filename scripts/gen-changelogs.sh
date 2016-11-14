#!/usr/bin/env bash
currentBranch=`git rev-parse --abbrev-ref HEAD`
githubToken=$1

if [ -z ${githubToken} ]; then
    echo -n "Enter your github token and press [ENTER]: "
    read githubToken
fi

echo "**************************************"
echo "Current Branch: $currentBranch"
echo "**************************************"
github_changelog_generator -t ${githubToken}
echo "**************************************"

