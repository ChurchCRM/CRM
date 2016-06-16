#!/bin/bash

# compile a list of all files to check
OUT=$(find . -type d \( -name vendor -o -name adminlte \) -prune -o   \( -name "*.php" -or -name "*.sh" -or -name "*.js" -or -name "*.css" -or -name "*.sql" -or -name "*.md" \) -print0 | xargs -0 grep -l $'\r$' -U -l)

# if the return code is 0 egrep found a match - this is bad
if [ $? == 0 ]
then
  echo "The following files have DOS line endings:"
  echo $OUT
  echo -e "\033[41m\033[1;37m Failed \033[0m"
  exit 1
fi

# otherwise we output a bright green inverted "Passed"
echo -e "\033[42m\033[1;37m Passed \033[0m"
