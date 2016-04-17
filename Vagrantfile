# -*- mode: ruby -*-
# vi: set ft=ruby :

##################################################
#
# Startup vagrant on 192.168.33.10
# Map the src dir to the default httpd www/public dir


Vagrant.configure("2") do |config|

  config.vm.box = "scotch/box"
  config.vm.network "private_network", ip: "192.168.33.10"
  config.vm.hostname = "scotchbox"
  config.vm.synced_folder "src", "/var/www/public", :mount_options => ["dmode=777", "fmode=666"]

   config.vm.provision :shell, :path => "vagrant/bootstrap.sh"
end
