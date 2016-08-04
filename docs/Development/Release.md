# Create a release

## Do a clean clone of the branch 
 * start the vagrant box (this will download all the 3rd party files)
 * ssh into the vagrant box
 * 'cd /vagrant'
 * 'composer install'
 * 'vendor/bin/phing' this will do the following
 ** regen messages.po based on the latest files
 ** build zip package
 ** generate the change log
 ** update the version #s to next version

## check in translation file 

 * commit changes to messages.po 
 * push to master 

##  Create a github release   

https://github.com/ChurchCRM/CRM/releases

 * Ensure you select the correct branch
 * Enter version # as the tag and subject 
 * point to the change log 
 * Upload zip file
 * Publish the release 

## Update release notes 
 * 'vendor/bin/phing change-log' this will do genreate the logs after the tags are created
 * commit changes to CHANGELOG.md
 * Update git release to point to version in change log

## Update milestones

https://github.com/ChurchCRM/CRM/milestones

 * Close version milestone 
 * create next version milestone 
 
## Merge into develop 
 * Create PR
 * Approve and merge PR
   
## Rev to the next version 
 * Update app version in build.xml
 * create new version  db scripts 
 
 
  

