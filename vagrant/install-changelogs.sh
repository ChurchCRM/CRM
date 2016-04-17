#!/usr/bin/env bash

echo "============= Install and run github change-log generator =================================="

sudo apt-get install -y ruby

gem install github_changelog_generator

cd ..

github_changelog_generator -t 64f5ebabc85c0533ed7e69f0c8ecf8c5981a1c50

echo ======================================================================
echo === Generat changelog by command
echo === github_changelog_generator -t 64f5ebabc85c0533ed7e69f0c8ecf8c5981a1c50
echo =====================================================================

