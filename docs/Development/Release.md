# Release Rules

1. ChurchCRM Major (0.x.x) and Minor (x.0.x) releases will only occur on Monday, Tuesday, or Wednesday nights.

  1.  If the release is blocked by a P0 bug, then the release will be delayed until the next release window.

  2.  Release Candidates will be made available one week before the targeted release date.

2. ChurchCRM patch builds (x.x.0) may be released at any time, upon validation that the patch:

  a.  Fixes the issue for which it is intended

  b.  Does not introduce any new issues (or features)

  c.  Does not significantly alter the code base

# Bug Definitions
 
## P0

  * Cause database corruption.
  * Prevents backup or restore the databse
  * Expose sensitive data
  * Prevents User Login
  * App crashes


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

## 2. Review Locales

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
  
    First edit BuildConfig.json, and set ```POEditor.token``` to your personal POEditor API access token.  Then, run:

    ```
    npm run locale-download
    ```

  4. Check in translation file 

    1. Create a new branch from master
    2. Commit changes to messages.po 
    3. Push the branch to GitHub
    4. Merge the branch to Master.  Note the commit hash - we'll want to compare this against the demosite later.

## 3. Test the build!
   
  This testing should be done to ensure there are no last-minute "showstopper" bugs or a bad build
  
  1. Test Build on Master http://demo.churchcrm.com/master  
    
  2. Test the zip file in the vagrant QA environment:
    
    1. After creating the release zip archive, copy it to /vagrant-qa

    2. Edit /vagrant-qa/VersionToLaunch.  Place the filename as the only uncommented line of the file

    3. From the /vagrant-qa directory, run 

    ```
     vagrant up
    ```

    4. A new Vagrant VM wil be started on http://192.168.10.12 with the contents of the release zip.  Test major functionality in this QA environment

    5. After testing a clean install of the release, test an in-place upgrade of the release.

      1.  Place a restore of a previous version of ChurchCRM in the /vagrant-qa directory.  The file must be named ```ChurchCRM-Database.sql```.  

      2.  Run ```vagrant provision```, and the vagrant VM will be re-loaded with the database pre-staged

      3.  Attempt to log into the vagrant-qa box.  The in-place upgrade routines should upgrade the database.


## 5.  Create a GitHub release/tag

  https://github.com/ChurchCRM/CRM/releases

  * Ensure you select the correct branch, and that the current commit hash matches the commit you created in step 4.4
  * Enter version # subject
  * Select tag as commit 
  * Point to the change log 
  * download zip from http://demo.churchcrm.io master list
  * Upload zip file 
  * Save the release as pre-release

## 6. Update release notes and version number

  After the tag has been created, update the change log and version number

  ```
  npm run changelog-gen
  grunt updateVersions 
  ```

  * Commit the changes to a new branch titled ```<new-version-number>-starting``` if your new version has a schema change.
  * Update git release so it points to the latest version in the change log

## 7. Update milestones

  https://github.com/ChurchCRM/CRM/milestones

  * Close version milestone 
  * Create next version milestone 
 
## 8. Merge master into develop 

  * Create a new branch to merge master into develop
  * Create a PR to get approval for the merge - sometimes regressions can sneak in here, so be careful!
 
  

