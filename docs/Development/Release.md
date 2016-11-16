# Process for creating a ChurchCRM Release

## 1. Clean and update the local working copy

  1.  Change to the ChurchCRM working directory, and destroy any existing vagrant boxes

    ```
    vagrant destroy -f
    ```

  2.  Checkout the branch to be released, and pull any updates

    ```
    git checkout master
    git pull
    ```

  3.  Remove all extra files to ensure a clean build

    ```
    git reset --hard
    git clean -xdf
    ```

## 2. Build ChurchCRM

  1. Start the vagrant box to build all prerequisites.  When build is complete, log into the box on SSH and cd to /vagrant

    ```
    vagrant up
    vagrant ssh
    cd /vagrant
    ```
    
  2. Update the Languages files by running: 
  
    ```
    npm run locale-gen
    ```
    
    This will create a new /src/locale/messages.po file.  If you have access rights, upload this file to POEditor.com

  3. Pull updated translation strings from POEditor.com
  
    First edit Gruntfile.js, and set ```poeditor.options.api_token``` to your personal POEditor API access token.  Then, run:

    ```
    npm run locale-download
    ```

  4. Check in translation file 

    1. Create a new branch from master
    2. Commit changes to messages.po 
    3. Push the branch to GitHub
    4. Merge the branch to Master.  Note the commit hash - we'll want to compare this against the demosite later.

  5. After checking in translation updates, run the NPM build script

    ```
    npm run package
    ```

    This will run the following actions:
      * Generate code signatures
      * Build zip package

## 3. Test the build!
   
  This testing should be done to ensure there are no last-minute "showstopper" bugs or a bad build
    
  1. Update the demosite using 

    ```
    npm run demosite
    ```
    
    The Demosite push key will be required.  Feel free to kick the tires on the demo site at this point one last time.  The commit hash on the demo site landing page should match the commit hash from step 4.4

  2. Test the zip file in the vagrant QA environment:
    
    1. After creating the release zip archive, copy it to /vagrant-qa

    2. Edit /vagrant-qa/VersionToLaunch.  Place the filename as the only uncommented line of the file

    3. From the /vagrant-qa directory, run 

    ```
     vagrant up
    ```

    4. A new Vagrant VM wil be started on http://192.168.10.12 with the contents of the release zip.  Test major functionality in this QA environment

  3. Test the release package on your own testing server



## 5.  Create a GitHub release/tag

  https://github.com/ChurchCRM/CRM/releases

  * Ensure you select the correct branch, and that the current commit hash matches the commit you created in step 4.4
  * Enter version # as the tag and subject 
  * Point to the change log 
  * Upload zip file
  * Save the release as pre-release

## 6. Update release notes and version number

  After the tag has been created, update the change log and version number

  ```
  npm run postpackage
  ```

  * Also, edit ```/src/mysql/upgrade.json``` to reflect the current upgrade path.
  * Commit the changes to a new branch titled ```<new-version-number>-starting```
  * Update git release so it points to the latest version in the change log

## 7. Update milestones

  https://github.com/ChurchCRM/CRM/milestones

  * Close version milestone 
  * Create next version milestone 
 
## 8. Merge master into develop 

  * Create a new branch to merge master into develop
  * Create a PR to get approval for the merge - sometimes regressions can sneak in here, so be careful!
 
  

