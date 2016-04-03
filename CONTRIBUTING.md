# How to contribute
We love to hear ideas from other ChurchInfo and ChurchCRM users!  It's what makes this platform so great and versatile.  If you have an idea to contribute, please take a few moments to share it with us!

## Getting Started

* Make sure you have a [GitHub account](https://github.com/signup/free)
* Submit a ticket for your issue, assuming one does not already exist.
  * Clearly describe the issue including steps to reproduce when it is a bug.
  * Make sure you fill in the earliest version that you know has the issue.
* Fork the repository on GitHub into your personal account.
* Install [vagrant](http://docs.churchcrm.io/en/develop/Development/Vagrant/) so you can test your changes in a *safe* environment.

## Making Changes

* Create a topic branch from where you want to base your work.
  * Use the following logic to determine your branch's base:
    * For new features, use develop
    * For bug fixes to existing features, use master
  * To quickly create a topic branch based on master; `git checkout -b fixes-issue-#<your issue number>`. Please avoid working directly on the `master` branch, as this makes PRs difficult
* Make commits of logical units.  "Commit Early, Commit Often" is a great motto.
* Check for unnecessary whitespace with `git diff --check` before committing.
* Changes to ChurchCRM will trigger version number changes in accordance with [Semantic Versioning 2.0.0](http://semver.org/)

## Submitting Changes

* Push your changes to a topic branch in your fork of the repository.
* Submit a pull request to the repository in the ChurchCRM organization.
    * Please include a screenshot or screencast if your changes affect the UI/UX ([LICEcap](http://www.cockos.com/licecap/) is a free animated GIF screen cast application)
* The core team looks at Pull Requests on a regular basis.
* After feedback has been given we expect responses within two weeks. After two
  weeks we may close the pull request if it isn't showing any activity.
  
## Documentation

Please familiarize yourself with the [documentation](http://docs.churchcrm.io/en/latest/) for the part(s) of code that you're changing.

* If you're changing anything in the API, please update the API documentation.  
* If you are changing something that affects the user interface, please update the appropriate documentation and help files to ensure continued user friendliness of the application.
  
## Code / Style Guide

### API
  The API is built with [Slim version 2.0](http://docs.slimframework.com/)

### UI Standards

*  We use the [AdminLTE theme](https://almsaeedstudio.com/preview) to generate a consistent UX for our users.  Before you make any UI changes please review the [AdminLTE documentation](https://almsaeedstudio.com/themes/AdminLTE/documentation/index.html) for the best way to leverage the theme's built in JavaScript and CSS. 
*  AdminLTE contains many JavaScript [Plugins](https://almsaeedstudio.com/themes/AdminLTE/documentation/index.html#plugins) (including [JQuery](http://www.w3schools.com/jquery/) and [Bootstrap](http://www.w3schools.com/bootstrap/)), so before adding any external components, please evaluate the plugins already in the project.

### General Code Formatting

*  We use [editorconfig](http://editorconfig.org/) to normalize code styling.
*  Not all code styles are supported for normalization within editorconfig, so please refer to the list below
*  All files use LF (Unix) line endings.  CI Tests will fail for any PR containing CRLF or CR line endings.

####  Tabs and Indents
* All tabs are represented as spaces
* A single "tab" is expanded to 2 spaces

#### Alignment 
* A new line should follow the following clauses:
    * else
    * elseif
    * while
    * finally
    * catch
* New lines should not follow class or scope definitions like "class", "public", or "private" 

#### Braces
* All open braces should be on the same line as the control statement:
    * Class definitions
    * Method declarations
    * if, else, elseif
    * for, foreach
    * while
    * do 
    * switch 
    * try, catch, finally
* All close braces should be on their own line.

#### Spaces 
* A space should occur:
    * Inside the parentheses in the following statements:
        * if, elseif
        * for, foreach
        * while
        * catch
        * switch
    * Before and after the following elements:
        * Binary operators ( < > == )
        * Ternary Operators ( $b ? $a : $b )
        * String Concatenation Operator ' . '
        * Key => Value Operator
        * Assignment Operator ($b = 5)
    * After
        * Comma
        * Semicolon
        * Type-casts
        * Short PHP Tag
    * Before
        * Close PHP Tag


### PHP Tags

* We don't use PHP short tags
* We do use <?= in place of <?php echo.

### JavaScript

* We have a window.CRM object
    *window.CRM.root represents the  $sRootPath path as defined in Include/Config.php