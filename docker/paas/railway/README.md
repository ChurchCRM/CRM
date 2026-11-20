# Railway template setup

Create the template in Railway's template editor after the image persistence
support has been released. Railway's per-service `railway.json` does not define
a multi-service template; the public deploy button needs a published template ID.

Create both services in the same project and environment:

| Service | Image | Persistent volume |
| --- | --- | --- |
| churchcrm | `churchcrm/crm:latest-php8-apache` | `/var/lib/churchcrm` |
| mariadb | `mariadb:11.4.5` | `/var/lib/mysql` |

Set these variables on `mariadb`:

```text
MARIADB_DATABASE=churchcrm
MARIADB_USER=churchcrm
MARIADB_PASSWORD=${{secret(32)}}
MARIADB_ROOT_PASSWORD=${{secret(32)}}
```

Use Railway's template secret generator for both passwords. Keep the database
on the private network with no public TCP proxy. On `churchcrm`, set
`CHURCHCRM_PERSISTENT_DATA=1` and `RAILWAY_RUN_UID=0` (Railway volumes are
root-owned). Generate a public HTTPS domain targeting port **8080**. Keep one
replica per service and disable app sleeping for a church's production install.

In `/setup/`, enter the database service's private hostname, port `3306`,
database/user `churchcrm`, and its generated `MARIADB_PASSWORD`. Leave the
install path empty and use the public HTTPS application URL.

Complete the verification in [the PaaS guide](../README.md) on Railway before
publishing the template. Then record its real template ID in that guide and
the docs install page. The partner application and standalone churchcrm.io page
are separate steps after deployment verification; no partner status is implied.
