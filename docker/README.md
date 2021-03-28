Getting Started with Docker for Development
===========================

** THIS DOCKER CONFIGURATION IS INTENDED FOR DEVLOPMENT & DEVELOPMENT PURPOSES ONLY**

The main difference between the Dev and Test dockers is that the Dev has NPM, Node, composer, and Cypress installed and running inside the docker.


Development
-------------

These are the steps needed to develop ChurchCRM with Docker

##Requirements

* Docker
* Docker Compose
* GIT

##Steps

1. Clone ChurchCRM Repo `git clone git@github.com:ChurchCRM/CRM.git`
2. build docker `npm run docker-dev-build`
3. run docker `npm run docker-dev-start`
4. ssh into docker, you will need the docker id or use docker desktop to build the ssh comand
   example ` docker container ls` then find the churchcrm id then use it in `docker exec -it xxxxxx /bin/sh`
5. cd into src code `cd /home/ChurchCRM`
6. build church crm web and php code `npm run deploy`
10. stop docker `npm run docker-dev-stop`

Testing
-----------------

if you are developing on your local dev system and testing via docker, use the following:

##Requirements

* Docker
* Docker Compose
* GIT
* node / npm
* composer

1. Clone ChurchCRM Repo `git clone git@github.com:ChurchCRM/CRM.git`
2. build code `npm run deploy`
3. build docker `npm run docker-test-build`
4. run docker `npm run docker-test-start`
5. test code `npm run docker-ci`
10. stop docker `npm run docker-test-stop`
