#!/bin/bash

egrepTest ()
{
    # compile a list of all files to check
    OUT=$(find . -type d \( -path "./.*" -o -path "./src/skin" -o -path "./src/vendor" -o -path "./src/Include" \) -prune -o \
               -type f -and \( -name "*.php" -o -name "*.sh" -o -name "*.js" -o \
                               -name "*.css" -o -name "*.sql" -o -name "*.md" \) -print0 |\
        xargs -0 egrep $1 -U -l)

    # if the return code is 0 egrep found a match - this is bad
    if [ $? == 0 ]
    then
      echo "The following files $2:"
      echo $OUT
      echo -e "\033[41m\033[1;37m Failed \033[0m"
      exit 1
    fi

    # otherwise we output a bright green inverted "Passed"
    echo "No files $2 :-)"
    echo -e "\033[42m\033[1;37m Passed \033[0m"
}
