Getting Started with Docker
===========================

Requirements
------------

* Docker
* Docker Compose

Configuration
-------------

- Copy the `example.env` file to the directory where you want to run the
  containers from, and rename it `.env`
- Edit your `.env` file and set your database settings in there
- Copy the `docker-compose.example.yaml` file to the directory where you want
  to run the containers from, and rename it `docker-compose.yaml`

Running ChurchCRM
-----------------

Run Docker Compose in the same directory as the docker-compose.yaml file:

```bash
$ docker-compose up -d
```

NGINX is exposed on port 8080 of your local machine. If you point an external
reverse proxy to this port, you can serve ChurchCRM off your own domain. We
recommend setting up HTTPS.
