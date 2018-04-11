# -*- mode: ruby -*-
# vi: set ft=ruby :

##################################################
#
# Startup vagrant on 192.168.33.10
# Map the src dir to the default httpd www/public dir


Vagrant.configure("2") do |config|

  config.vm.box = "ChurchCRM/box1.4"
  config.vm.box_url = "https://box.churchcrm.io/churchcrm1.4.box"
  config.vm.network "private_network", ip: "192.168.33.10"
  config.vm.hostname = "ChurchCRM"
  config.vm.synced_folder "src", "/var/www/public", :mount_options => ["dmode=777", "fmode=666"]
  config.vm.synced_folder "node_modules", "/home/vagrant/host_node_modules", :mount_options => ["dmode=777", "fmode=666"]


  config.vm.provision :shell, :path => "vagrant/bootstrap.sh", :args =>["php7"]
end
