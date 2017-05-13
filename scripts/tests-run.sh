#!/bin/bash

#Hints here: http://codegist.net/code/sauce%20connect%20setup/

SC_READYFILE=/tmp/sc/ready

if [[ -z "${TRAVIS}" ]]; then
  echo "Not TravisCI - Manually starting Sauce Connect"
  /tmp/sc/sc-4.4.6-linux/bin/sc --readyfile ${SC_READYFILE} 1>&
  while [ ! -f ${SC_READYFILE} ]; do
    echo "Waiting for Sauce Connect readyfile"
    sleep .5
  done
fi

if [[ "${SAUCE_USERNAME}" && "${SAUCE_ACCESS_KEY}" ]]; then
  echo "SAUCE"
  export BEHAT_PARAMS='{"extensions" : {"Behat\\MinkExtension" : {"selenium2" : { "wd_host":"'${SAUCE_USERNAME}':'${SAUCE_ACCESS_KEY}'@ondemand.saucelabs.com/wd/hub"}}}}'
else
  echo "NO SAUCE"
  export BEHAT_PARAMS='{"extensions" : {"Behat\\MinkExtension" : {"goutte" : "~","selenium2":"~"}}}'
fi

#echo $BEHAT_PARAMS

cd tests/behat/
  ../vendor/bin/behat