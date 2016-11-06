#!/usr/bin/env bash
cd /vagrant
sudo /usr/local/bin/composer install
sudo apt-get install -y ruby
gem install multi_json github_changelog_generator
