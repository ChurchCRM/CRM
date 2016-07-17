##Generate new message.po file 

- ssh into vagrant box 
- cd /vagrant/src 
- run '$ xgettext --from-code=UTF-8 -o locale/messages.po *.php email/*.php Include/*.php Reports/*.php sundayschool/*.php'
- review changes in /vagrant/src/locale/messages.po 
- commit messages.po

