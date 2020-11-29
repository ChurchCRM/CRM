Getting Started with Docker for Development
===========================

** THIS DOCKER CONFIGURATION IS INTENDED FOR DEVLOPMENT PURPOSES ONLY**
 

Requirements
------------

* Docker
* Docker Compose

Configuration
-------------

- Copy the `example.env` file to the directory where you want to run the
  containers from, and rename it `.env`
- Edit your `.env` file and set your database settings in there

Running ChurchCRM
-----------------

```
docker-compose -f docker-compose.develop.yaml up
```