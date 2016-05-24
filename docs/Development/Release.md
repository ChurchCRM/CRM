# Create a release

## Do a clean clone of the branch 
 * create zip file of the  src dir named ChurchCRM-2.XX.XX.zip (matching version) 
 * rename the src dir to churchcrm

##  Create a github release   

https://github.com/ChurchCRM/CRM/releases

 * Ensure you select the correct branch
 * Enter version # as the tag and subject 
 * point to the change log 
 * Upload zip file
 * Publish the release 

## Update release notes 
 
 * ssh into a vagrant box 
 * run `cd /vagrant` 
 * run  `vagrant/install-changelogs.sh`
 * run  `github_changelog_generator -t 64f5ebabc85c0533ed7e69f0c8ecf8c5981a1c50`
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
 
 
  

