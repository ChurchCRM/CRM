#!/usr/bin/env bash

buildversion=`grep version /vagrant/src/composer.json | cut -d ',' -f1 | cut -d'"' -f4`
currentBranch=`git rev-parse --abbrev-ref HEAD`
nextVersion=$1

if [ -z ${nextVersion} ]; then
    echo -n "Current Version ${buildversion} on ${currentBranch} branch: Enter the next version and press [ENTER]: "
    read nextVersion
fi

echo "**************************************"
echo "Current Branch: $currentBranch"
echo "Current Version: $buildversion"
echo "Next Version: $nextVersion"
echo "**************************************"
replace "${buildversion}" "${nextVersion}" -- /vagrant/src/composer.json
echo "**************************************"

