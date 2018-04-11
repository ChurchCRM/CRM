#! /bin/bash

# Oh, the terrible things we need to do to make NPM work happily
# and still supply developer-side react tooling on the VM Host 
# While preserving the automation on both Travis CI and local Vagrant.

mountpoint /home/vagrant/host_node_modules > /dev/null
ISMOUNTPOINT=$?

if [ $ISMOUNTPOINT -eq 0 ]; then 
    echo "/home/vagrant/host_node_modules is a mountpoint - sync node_modules with host"
    cp  -R -L /home/vagrant/node_modules/* /home/vagrant/host_node_modules
else
    echo "/home/vagrant/host_node_modules is not a mountpoint - do nothing"
fi
