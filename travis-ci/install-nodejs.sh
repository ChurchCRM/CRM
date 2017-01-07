#!/usr/bin/env bash

set -e

echo "=========================================================="
echo "===============   nodejs install         ================="
echo "=========================================================="
# setup for node 6/7 (v6.9 is an LTS release, so we'll support it for the next time)
sudo rm -rf ~/.nvm
curl -o- https://raw.githubusercontent.com/creationix/nvm/v0.33.0/install.sh | bash
nvm install 6.9
nvm alias default 6.9
