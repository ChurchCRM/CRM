#!/bin/bash

source $(dirname $0)/testlib.sh
egrepTest '
$' 'have DOS line endings'
