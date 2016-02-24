# How to contribute
We love to hear ideas from other ChurchInfo and ChurchCRM users!  It's what makes this platform so great and versatile.  If you have an idea to contribute, please take a few moments to share it with us!

## Getting Started

* Make sure you have a [GitHub account](https://github.com/signup/free)
* Submit a ticket for your issue, assuming one does not already exist.
  * Clearly describe the issue including steps to reproduce when it is a bug.
  * Make sure you fill in the earliest version that you know has the issue.
* Fork the repository on GitHub into your personal account.

## Making Changes

* Create a topic branch from where you want to base your work.
  * This is usually the develop branch.
  * To quickly create a topic branch based on master; `git checkout -b fixes-issue-#<your issue number>`. Please avoid working directly on the `master` branch, as this makes PRs difficult
* Make commits of logical units.  "Commit Early, Commit Often" is a great motto.
* Check for unnecessary whitespace with `git diff --check` before committing.

## Submitting Changes

* Push your changes to a topic branch in your fork of the repository.
* Submit a pull request to the repository in the ChurchCRM organization.
* The core team looks at Pull Requests on a regular basis.
* After feedback has been given we expect responses within two weeks. After two
  weeks we may close the pull request if it isn't showing any activity.
  
## Documentation

* If you're changing anything in the API, please update the API documentation.  
* If you are changing something that affects the user interface, please update the appropriate documentation and help files to ensure continued user friendliness of the application.
  
## Style Guide

### Spacing

*  We represent "tab" as 4 spaces.  We have unit tests to ensure that the code style stays clean.  Please configure your editor accordingly.

### HTML

*  Please ensure all HTML nodes are indented appropriately

### PHP Tags

* We don't use short tags.
* If you find any, please replace them
* We do use <?= in place of <?php echo.

### JavaScript

* We have a window.CRM object
    *window.CRM.root represents the  $sRootPath path as defined in Include/Config.php