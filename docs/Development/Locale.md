## Want to help translate this project?
[Join the Project on POEditor](https://poeditor.com/join/project/RABdnDSqAt)

## The system uses gettext for localization

checkout [GNU gettext Manual](http://www.gnu.org/software/gettext/manual/) 

## Generate Master Localization file 

- ssh into vagrant box 
- cd /vagrant
- Run `npm run locale-gen`
- Review changes in /vagrant/src/locale/messages.po 
- Commit messages.po
- Create a Pull Request for changes
- Upload to POEditor.com
- Tag the terms with the release version

## Update Localization
- got to poeditor.com
- update locale values for terms
- download po and mo file to your locale server for testing

## Download Localization 

- Go to POEditor.com and genrate an API Key
- Add the API Key to Build BuildConfig.json
- ssh into vagrant box
- cd /vagrant
- Run `npm run locale-download`
- Review Changes 
- Commit Changes
- Create a Pull Request for changes

## Change the Language

- See [Localization](../Installation/Localization.md) 



