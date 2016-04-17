
## Shell Scripts 

1. start vagrant - vagrant up on the project root dir
2. login to vagrant   - ssh vagrant@192.168.33.10 / vagrant
3. cd /vagrant/vagrant 

## UI Dependency Management

see composer.json in the root dir.  

## Skins
and see vagrant/build-skin.sh

to add/update a new ui dependency

1. update composer.json
2. update build-skin.sh if needed
3. ssh into vagrant box (vagrant/vagrant)
4. run ./vagrant/build-skin.sh
5. review changes
6. commit changes

## SASS 

see vagrant/install-sass.sh and vagrant/build-sass.sh
 
1. update src/skin/churchcrm.scss as needed
2. ssh into vagrant box (vagrant/vagrant)
3. run install-sass.sh once
4. run build-sass.sh
5. review changes
6. commit changes
