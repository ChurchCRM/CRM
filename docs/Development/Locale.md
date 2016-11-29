##Want to help translate this project?
[Join the Project on POEditor](https://poeditor.com/join/project/RABdnDSqAt)

##The system uses gettext for localization

checkout [GNU gettext Manual](http://www.gnu.org/software/gettext/manual/) 

##System locale 

1. Ensure System has correct locals ```sudo locale-gen es_ES```

    The hosting system must have the correct locale as gettext depends on system libs for localization
     - see [HowTo : Change Locale (Language and Character Set) in Linux](http://www.shellhacks.com/en/HowTo-Change-Locale-Language-and-Character-Set-in-Linux)

2. Click the gears icon on the top right corner
3. Select Settings tab
4. Click System Settings  
3. Select ```Localization``` tab
3. Change ```sLanguage``` to one of the available languages in the drop down. 

##Generate new message.po file 

- ssh into vagrant box 
- cd /vagrant
- Run `npm run locale-gen`
- Review changes in /vagrant/src/locale/messages.po 
- Commit messages.po
- Upload to POEditor.com 

