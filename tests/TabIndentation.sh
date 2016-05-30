#!/bin/bash

source $(dirname $0)/testlib.sh
egrepTest '^\s*\t' 'use tabs for indentation (please use 4 spaces)'
