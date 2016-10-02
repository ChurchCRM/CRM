##Generate new message.po file 

- ssh into vagrant box 
- cd /vagrant/src 
- Run '$ xgettext --from-code=UTF-8 -o locale/messages.po *.php email/*.php Include/*.php Reports/*.php sundayschool/*.php'
- Review changes in /vagrant/src/locale/messages.po 
- Commit messages.po

