#!/bin/bash

#Hints here: http://codegist.net/code/sauce%20connect%20setup/

echo "Scanning composer for vulnerabilities"
cd tests
php security-checker.phar security:check /vagrant/src/composer.lock
php security-checker.phar security:check /vagrant/tests/composer.lock


SingleTest=./behat/features/$1
echo $SingleTest

if [ -f $SingleTest ]; then
  echo "Running single test: $1"
else
  echo "Running full test suite"
fi

SC_READYFILE=/tmp/scready
SC_LOG=/tmp/sauce.log


if [[ "${SAUCE_USERNAME}" && "${SAUCE_ACCESS_KEY}" ]]; then
  echo "SAUCE"

  if [[ -z "${TRAVIS}" ]]; then

    echo "Not TravisCI - Manually starting Sauce Connect"
    /tmp/sc/sc-4.4.6-linux/bin/sc --readyfile ${SC_READYFILE}  &>${SC_LOG} &

    while [ ! -f ${SC_READYFILE} ]; do
      echo "Waiting for Sauce Connect readyfile"

      if grep -q Goodbye "${SC_LOG}"; then
        echo "Sauce Connect unexpectedly terminated.  Please check ${SC_LOG}"
        exit
      fi

      sleep .5
    done
  fi

  export BEHAT_PARAMS='{"extensions" : {"Behat\\MinkExtension" : {"selenium2" : { "wd_host":"'${SAUCE_USERNAME}':'${SAUCE_ACCESS_KEY}'@ondemand.saucelabs.com/wd/hub"}}}}'
else
  echo "NO SAUCE"
  export BEHAT_PARAMS='{"extensions" : {"Behat\\MinkExtension" : {"goutte" : "~","selenium2":"~"}}}'
fi

#echo $BEHAT_PARAMS
if [ -f $SingleTest ]; then
  cd behat/
  ../vendor/bin/behat features/$1
else
  cd behat/
  ../vendor/bin/behat
fi
