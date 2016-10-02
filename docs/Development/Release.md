# Create a release

## Do a clean clone of the branch 
 * Start the vagrant box (this will download all the third party files)
 * ssh into the vagrant box
 * 'cd /vagrant'
 * 'composer install'
 * 'vendor/bin/phing' this will do the following
   * Regenerate messages.po based on the latest files
   * Build zip package
   * Generate the change log
   * Update the version #s to next version


## Update the demo site
 * Git checkout the branch you want to push to demo site
 * start the vagrant box (this will download all the 3rd party files)
 * ssh into the vagrant box
 * 'cd /vagrant'
 * 'composer install'
 * 'vendor/bin/phing package' this will do the following
   * build zip package
 * 'vendor/bin/phing demosite' this will do the following
   * upload the zip file to the demo site
   * run server-side scripts to unpack the zip file
   * copy a pre-configured config.php file 
   * reset the demo database
   * set the demo password to admin / george

## Check in translation file 

 * Commit changes to messages.po 
 * Push to master 

##  Create a GitHub release   

https://github.com/ChurchCRM/CRM/releases

 * Ensure you select the correct branch
 * Enter version # as the tag and subject 
 * Point to the change log 
 * Upload zip file
 * Publish the release 

## Update release notes 
 * 'vendor/bin/phing change-log' this will generate the logs after the tags are created
 * Commit changes to CHANGELOG.md
 * Update git release so it points to the latest version in the change log

## Update milestones

https://github.com/ChurchCRM/CRM/milestones

 * Close version milestone 
 * Create next version milestone 
 
## Merge into develop 
 * Create PR
 * Approve and merge PR
   
## Rev to the next version 
 * Update app version in build.xml
 * Create new version  db scripts 
 
 
  

