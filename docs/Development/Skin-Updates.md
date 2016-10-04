
## Shell Scripts 

1. Start vagrant - vagrant up on the project root dir
2. Log in to vagrant   - ssh vagrant@192.168.33.10 / vagrant
3. cd /vagrant/vagrant 

## UI Dependency Management

See composer.json in the root dir.  

## Skins
See vagrant/build-skin.sh

To add/update a new ui dependency:

1. Update composer.json
2. Update build-skin.sh if needed
3. ssh into vagrant box (vagrant/vagrant)
4. Run ./vagrant/build-skin.sh
5. Review changes
6. Commit changes

## SASS 

See vagrant/install-sass.sh and vagrant/build-sass.sh
 
1. Update src/skin/churchcrm.scss as needed
2. ssh into vagrant box (vagrant/vagrant)
3. Run install-sass.sh once
4. Run build-sass.sh
5. Review changes
6. Commit changes
