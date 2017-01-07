#!/usr/bin/env bash

set -e

echo "=========================================================="
echo "===============   nodejs install         ================="
echo "=========================================================="
# setup for node 6/7 (v6.9 is an LTS release, so we'll support it for the next time)
rm -rf ~/.nvm 
git clone https://github.com/creationix/nvm.git ~/.nvm 
cd ~/.nvm && git checkout `git describe --abbrev=0 --tags`
source ~/.nvm/nvm.sh 
nvm install $TRAVIS_NODE_VERSION
npm install
