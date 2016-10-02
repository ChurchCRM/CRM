##The system uses gettext for localization

checkout http://www.gnu.org/software/gettext/manual/ 

##Localized Files
locale dir https://github.com/ChurchCRM/CRM/tree/master/src/locale

##System locale 
The hosting system must have the correct locale as gettext depends on system libs for localization
- see http://www.shellhacks.com/en/HowTo-Change-Locale-Language-and-Character-Set-in-Linux 


##Generate new message.po file 

- ssh into vagrant box 
- cd /vagrant/src 
- Run '$ xgettext --from-code=UTF-8 -o locale/messages.po *.php email/*.php Include/*.php Reports/*.php sundayschool/*.php'
- Review changes in /vagrant/src/locale/messages.po 
- Commit messages.po

