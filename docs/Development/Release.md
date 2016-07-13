# Create a release

## Do a clean clone of the branch 
 * start the vagrant box (this will download all the 3rd party files)
 * ssh into the vagrant box
 * 'cd /vagrant'
 * 'composer install'
 * 'vendor/bin/phing" to create the zip file
 * 'vendor/bin/phing change-log' to generate the change log

##  Create a github release   

https://github.com/ChurchCRM/CRM/releases

 * Ensure you select the correct branch
 * Enter version # as the tag and subject 
 * point to the change log 
 * Upload zip file
 * Publish the release 

## Update release notes 
 
 * commit changes to CHANGELOG.md
 * Update git release to point to version in chagelog

## Update milestones

https://github.com/ChurchCRM/CRM/milestones

 * Close version milestone 
 * create next version milestone 
 
## Merge into develop 
 * Create PR
 * Approve and merge PR
   
## Rev to the next version 
 * Update app version 
 * create new version  db scripts 
 
 
  

