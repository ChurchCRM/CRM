#!/usr/bin/env bash
githubToken=$1

if [ -z ${githubToken} ]; then
    echo -n "Enter your github token and press [ENTER]: "
    read githubToken
fi

sudo npm install github-release-notes@0.13.0 -g

gren changelog --generate --override --token=${githubToken}
