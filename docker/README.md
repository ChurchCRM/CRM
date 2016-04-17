Church CRM Docker
================

Docker Image for running Church CRM via Out-of-the-box LAMP image (PHP+MySQL)

Running your Church CRM Docker image
------------------------------

Start your image binding the external ports 80 and 3306 in all interfaces to your container:

	docker run -d -p 80:80 -p 3306:3306 churchwebcrm/crm

Usage
-----

Test your deployment:

get the ip of docker  via 
	
`c:\Program Files\Boot2Docker for Windows>boot2docker.exe ip`


The VM's Host only interface IP address is: 192.168.59.103

	curl http://{dockerip}/
	
Default login are `admin/changeme`	

Connecting to the bundled MySQL server from outside the container
-----------------------------------------------------------------

The first time that you run your container, a new user/database with `churchcrm` with all privileges will be created in MySQL with a `churchcrm` password.

