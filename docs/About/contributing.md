# Contributing to ChurchCRM

An introduction to contributing to the ChurchCRM project.

The project welcomes, and depends, on contributions from developers and users in the open source community. Contributions can be made in a number of ways, a few examples are:

- Code patches via pull requests
- Documentation improvements
- Bug reports and patch reviews

## Testing A Branch
As long as there is software, there is a need for software testers.  We're no different.  As we transition into an automated testing system, there's still a very real need for actual *human beings* to test new features, bug fixes, and other aspects of the software.
### Setting Up A Testing Machine
There's really only two system requirements for a testing machine:

1. Oracle Virtual Box
  * Oracle VirtualBox allows you to run virtual machines on your system for free.
2. Vagrant 
  * At a 10,000 ft view, Vagrant is a tool that automagically provisions a virutal machine in Oracle VritualBox with all of the prerequisites, settings, files, and other artifacts that are required for running an instance of ChurchCRM.  Since ChurchCRM is a web application, the "vagrant image" also includes a fully functional LAMP stack.
  
### Testing ChurchCRM

1. Check out the branch you're going to test.  You can either use the [GitHub Desktop Client](https://desktop.github.com/), or manually download the source from the GitHub Page, or click one of the following links:
  * Most often, we'll want help testing the [Development Branch](https://github.com/ChurchCRM/CRM/archive/develop.zip)
  * Sometimes we'll need to test something in the [Expiremental Branch](https://github.com/ChurchCRM/CRM/archive/experimental.zip)
2. If you downloaded a Zip file, please extract that ZIP to a directory
3. From a command line, navigate to the directory containing the files
4. Run the command "vagrant up"
5. Wait for the prompt that says ChurchCRM is now Hosted
6. Open a browser to (http://192.168.33.10)
7. Test the features in question 
8. [Report any issues](https://github.com/ChurchCRM/CRM/issues)
9. Clean up.  From the same command line, run "vagrant destroy" to remove all traces of the code you were just testing.


## Reporting an issue?

Please include as much detail as you can. Let us know your platform and ChurchCRM version. If the problem is visual (for example a theme or design issue) please add a screenshot and if
you get an error please include the the full error and traceback.


## Installing for development

[Need a vagrant box can you build use one](https://github.com/ChurchCRM/CRM/issues/16)


## Running the tests

[Need test so that the build systems can run] (https://github.com/ChurchCRM/CRM/issues/13)

## Submitting Pull Requests

Once you are happy with your changes or you are ready for some
feedback, push it to your fork and send a pull request. For a
change to be accepted it will most likely need to have tests and
documentation if it is a new feature. (once we have tests that is )
