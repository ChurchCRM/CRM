#!/bin/bash

# grep for ^M (DOS line endings) in all files recursively (except for the .git dir)
OUT=$(egrep '^M$' `ls` -R -l)

# if the return code is 0 egrep found a match - this is bad
if [ $? == 0 ]
then
  echo "The following files have DOS line endings:"
  echo $OUT
  echo -e "\e[1m\e[7m\e[91m Failed \e[0m"
  exit 1
fi

# otherwise we output a bright green inverted "Passed"
echo -e "\e[1m\e[7m\e[92m Passed \e[0m"
