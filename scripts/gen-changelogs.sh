#!/usr/bin/env bash
currentBranch=`git rev-parse --abbrev-ref HEAD`
hasGem=`gem list github_changelog_generator -i`
githubToken=$1

if [ -z ${githubToken} ]; then
    echo -n "Enter your github token and press [ENTER]: "
    read githubToken
fi

if  [ "${hasGem}" == "false" ]; then
    echo "********** Installing missing gem: Started"
    sudo apt-get install -y ruby
    gem install multi_json github_changelog_generator
    echo "********** Installing missing gem: Done"
fi

echo "**************************************"
echo "Generating Change-Log for  Branch: $currentBranch"
echo "**************************************"
github_changelog_generator -t ${githubToken}
echo "**************************************"

