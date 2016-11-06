# Process for creating a ChurchCRM Release

## 1. Clean and update the local working copy

  1.  Destroy any existing vagrant boxes

    ```
    vagrant destroy -f
    ```

  2.  Checkout the branch to be released

    ```
    git checkout master
    ```

  3.  Remove all extra files to ensure a clean build

    ```
    git reset --hard
    git clean -xdf
    ```

## 2. Build ChurchCRM

  1. Start the vagrant box to build all prerequisites

    ```
    vagrant up
    ```

  2. After the vagrant box is up, ssh into the box and run the build script

    ```
    vagrant ssh
    cd /vagrant
    ./vendor/bin/phing
    ```

    This will run the following actions:
      * Regenerate messages.po based on the latest files
      * Build zip package
      * Update the version numbers to next version

## 3. Test the build!
   
  This testing should be done to ensure there are no last-minute "showstopper" bugs or a bad build
    
  1. Update the demosite using 

    ```
    ./vendor/bin/phing demosite
    ```
    
    The Demosite push key will be required.  Feel free to kick the tires on the demo site at this point one last time.

  2. Test the zip file on your own ChurchCRM instance


## 4. Check in translation file 

  1. Create a new branch from master
  2. Commit changes to messages.po 
  3. Push the branch to GitHub
  4. Merge the branch to Master.  Note the commit hash.

## 5.  Create a GitHub release   

https://github.com/ChurchCRM/CRM/releases

 * Ensure you select the correct branch, and that the hash matches the commit you created in step 4.
 * Enter version # as the tag and subject 
 * Point to the change log 
 * Upload zip file
 * Publish the release 

## 6. Update release notes 

  After the tag has been created, update the change log.

  ```
  ./vendor/bin/phing demosite
  ```

  * Commit changes to CHANGELOG.md
  * Update git release so it points to the latest version in the change log

## 7. Update milestones

  https://github.com/ChurchCRM/CRM/milestones

  * Close version milestone 
  * Create next version milestone 
 
## 8. Merge master into develop 

  * Create PR
  * Approve and merge PR
   
## 9. Rev to the next version 

  * Create new version  db scripts 
 
 
  

