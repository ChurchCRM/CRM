# Self-serve Docker PaaS packaging

These are **deployment candidates**, pending the image release and live platform
verification required by [#11769](https://github.com/ChurchCRM/CRM/issues/11769).
They are not published catalog entries or working deploy buttons.

All candidates use `churchcrm/crm:latest-php8-apache`, port **8080**, and
`mariadb:11.4.5`, matching the production stack's database version. The database
has no public port. Each service needs its own persistent disk.

## Image prerequisite

The Apache image must include `churchcrm-entrypoint` and support
`CHURCHCRM_PERSISTENT_DATA=1` before these templates can be deployed. With this
opt-in, mount a writable disk at `/var/lib/churchcrm`:

| Application path | Disk path |
| --- | --- |
| `Include/Config.php` | `/var/lib/churchcrm/Config.php` |
| `Images/Person` | `/var/lib/churchcrm/Person` |
| `Images/Family` | `/var/lib/churchcrm/Family` |

The disk lives outside the web root. Photo access rules refresh from each image;
the application code remains in the image. Docker named volumes inherit UID 33
ownership from the image. On platforms with root-owned disks, arrange ownership
for UID 33 before setup. Railway supports `RAILWAY_RUN_UID=0`; the entrypoint
initializes ownership and Apache runs its workers as `www-data`.

Use this mode for a fresh installation. To migrate an existing installation,
back up the database, configuration and photos, then restore them into the disk
with UID 33 ownership. The entrypoint refuses to discard existing local data.
The production Compose file's existing mounts continue to work without opting in.

## Platform handoff

| Platform | Candidate | Destination / remaining work |
| --- | --- | --- |
| Coolify | [Compose](coolify/churchcrm.yaml) | `coollabsio/coolify`, `templates/compose/churchcrm.yaml`; add catalog logo |
| Dokploy | [Compose](dokploy/docker-compose.yml), [TOML](dokploy/template.toml) | `Dokploy/templates`, `blueprints/churchcrm`; add catalog metadata and logo |
| CapRover | [One-click app](caprover/churchcrm.yml) | `caprover/one-click-apps`, `public/v4/apps/churchcrm.yml` |
| Easypanel | [Generator](easypanel/index.ts), [metadata](easypanel/meta.yaml) | `easypanel-io/templates`, `templates/churchcrm`; generate `meta.ts`, add logo and real screenshot |
| Railway | [Template editor instructions](railway/README.md) | Publish verified multi-service template and record its ID |
| Render | [Blueprint](render/render.yaml) | Import the blueprint from a repository, then record the verified repository URL |

Easypanel requests static image tags. ChurchCRM's support policy requires the
latest build; obtain acceptance of the maintained tag before catalog submission.
Railway and Render disks require paid resources or available account credits.
Check current platform prices before deploying; these are not shared free demos.

## Complete setup

Enable HTTPS at the platform proxy, then open `/setup/` on the application domain.
Use port `3306`, database `churchcrm`, user `churchcrm`, and the generated
**application database password**, rather than the root password:

| Platform | Database host | Password location |
| --- | --- | --- |
| Coolify | `mariadb` | `SERVICE_PASSWORD_DATABASE` |
| Dokploy | `mariadb` | `DB_PASSWORD` |
| CapRover | `srv-captain--<app name>-db` | Database password chosen during installation |
| Easypanel | `<project>_<database service name>` | Database service `MARIADB_PASSWORD` |
| Railway | Database service's private hostname | Database service `MARIADB_PASSWORD` |
| Render | Database service's internal hostname from its dashboard | Database service `MARIADB_PASSWORD` |

Use the public HTTPS URL and leave the install path empty. Allow MariaDB to finish
initializing before checking the connection. Complete setup and change the
initial administrator password immediately. Keep configuration backups private:
`Config.php` contains the database password.

## Verification required before catalog PRs or deploy buttons

For **each platform**, record the date, platform version, application image digest,
database image digest, and a redacted deployment URL or deployment ID. Then:

1. Deploy the candidate and complete the real setup wizard using its generated
   credentials. Verify login and the database connection.
2. Create a person and family, and upload a photo to each. Verify both display
   when signed in and direct anonymous photo requests are denied.
3. Replace the application container while retaining its disk. Verify login
   opens without returning to setup, and both photos remain available.
4. Replace the database container retaining its disk. Verify the records remain.
5. Pull the latest application image and redeploy. Verify application code comes
   from the new image and configuration/photos remain intact.
6. Back up and restore the database and application disk into a separate test
   deployment. Verify login, records and photos there.
7. Check that the database has no public endpoint and the app uses HTTPS.

Local syntax checks and entrypoint tests do not count as platform verification.
After verification and catalog acceptance, add real deploy buttons to the docs
install guide and demo page. Link the standalone Railway guide from churchcrm.io
before applying to its Open Source Partner Program. Zeabur and Koyeb remain
optional follow-ups, per the latest issue scope.
