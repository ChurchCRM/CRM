#!/usr/bin/env bash

buildversion=`grep version /vagrant/src/composer.json | cut -d ',' -f1 | cut -d'"' -f4`
demoKey=$1
file=/vagrant/target/ChurchCRM-$buildversion.zip
currentBranch=`git rev-parse --abbrev-ref HEAD`
publishBranch=currentBranch
commitHash=`git log --pretty=format:'%H' -n 1`



if [ -f $file  ]; then

  if [ -z ${demoKey} ]; then 
    echo -n "Enter the demo site hook password and press [ENTER]: "
    read demoKey
  fi

 
  if  ! [[ $publishBranch == "develop" ||  $publishBranch == "develop"  ]]; then
    echo -n "Current branch is not master or develop. Enter branch to emulate:  (develop)"
    read publishBranch
    publishBranch=${publishBranch:-develop}
  fi
  echo "**************************************"
  echo "Beginning to publish demosite"
  echo "Publishing ZipArchive: $file"
  echo "Current Branch: $currentBranch"
  echo "Publishing as Branch: $publishBranch"
  echo "Current Commit Hash: $commitHash"
  echo "**************************************"
  result=`curl -s -F "demoKey=${demoKey}" -F "branch=${publishBranch}" -F "commitHash=${commitHash}" -F "fileupload=@${file}" http://demo.churchcrm.io/webhooks/DemoUpdate.php`
  echo "Publishing Result"
  echo $result
  echo "**************************************"
else
  echo "You must build the source before pushing to demo site"
fi

        
       