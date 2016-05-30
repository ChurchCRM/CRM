#!/bin/bash

source $(dirname $0)/testlib.sh
egrepTest '\s$' 'contain trailing whitespace'
