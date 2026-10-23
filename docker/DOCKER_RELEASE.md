# ChurchCRM Docker release images

This file is the release engineering reference. For running the images, see
the [Docker installation guide](https://docs.churchcrm.io/installation/docker)
and `docker/production/compose.yaml`.

Stable releases publish `churchcrm/crm:<version>-php8-apache` and
`churchcrm/crm:<version>-php8-fpm`. Only the release currently marked latest on
GitHub updates `latest-php8-apache` / `latest-php8-fpm`, and `latest`, which is
the Apache image. Prefer a version tag or image digest for production
deployments. Re-running a build can replace a version tag; DockerHub tag
immutability is a separate registry policy.

Apache is the supported variant. The FPM tags are published, but they require a
web server in front and ChurchCRM's routing depends on Apache's rewrite rules,
so reproducing it elsewhere is the operator's responsibility. CI builds the FPM
image but only smoke-tests it — no HTTP request ever reaches PHP-FPM — and no
browser suite runs against it.

## Runtime and ports

- Apache serves HTTP on **8080** and runs as `www-data` (UID/GID 33). The
  test/dev stages retain 80. `EXPOSE 80` in the prod image is inherited from the
  PHP base image and is not served.
- FPM runs as UID/GID 33 and speaks **FastCGI on 9000**, not HTTP. Never publish
  9000 to an untrusted network. A separate web server must serve the matching
  static assets and forward PHP requests to FPM on the private container network.
- Both images contain the compiled release, PHP extensions, and locales. They
  omit Xdebug and purge the inherited PHP compiler toolchain from the final
  filesystem. The upstream base layers still contain the original toolchain;
  purging does not reclaim bytes in those layers.

To preview the Apache setup page locally:

```bash
docker run --rm --cap-drop ALL --security-opt no-new-privileges \
  -p 127.0.0.1:8080:8080 churchcrm/crm:7.8.0-php8-apache
```

Replace the version with the one you are checking. This preview does not
configure a database or persist application data — it only confirms the image
boots and serves the setup page. For a real installation use
`docker/production/compose.yaml`, which pairs the image with MariaDB, persistent
photo volumes and health checks. Place a TLS reverse proxy in front for remote
access.

The files under `docker/examples/` illustrate alternative architectures. They
are **not** working release-image recipes: they build from local Dockerfiles
rather than the published tags, and the nginx example's build context is wrong
for the directory it lives in. `docker/examples/nginx/default.conf` routes each
Slim front controller to its own `index.php`. A new directory with its own
`index.php` has to be added there. The image allowlist uses `allow all` so it
does not inherit the parent `deny all`, and each bare front-controller URL
redirects to the trailing-slash form so `/setup` does not land on a JSON 404.
An empty named volume normally receives image contents on first mount; a bind
mount obscures the contents, and a reused named volume keeps stale application
code across image upgrades. Do not persist the whole document root as an upgrade
strategy. Keep nginx and FPM on the same application version.

The FrankenPHP Dockerfile and example are unsupported: no image is published and
no workflow builds them, so their base image can break without anything noticing.

## Writable files and upgrades

Application files currently remain writable by UID 33 to support ChurchCRM's
installer, updater and plugin operations. Non-root execution therefore does not
prevent a compromised PHP process from changing application code. A read-only
root filesystem needs an explicit writable-path design and application-level
setup, upload, plugin and upgrade testing before it can become the default.
Mount an existing production `Include/Config.php` read-only where possible, keep
credentials out of images, and provision volume ownership for UID/GID 33.
Use image replacement for container upgrades; back up the database, configuration
and uploaded data first.

The persistence and restore behaviour has now been exercised against
`7.8.0-php8-apache`, and `docker/production/compose.yaml` is the result. What was
verified: the database and photo volumes survive `--force-recreate` and `down`
followed by `up`; a `mariadb-dump --single-transaction` round trip restores into
a fresh volume (all 59 tables are InnoDB); and re-running the setup wizard
against a populated database is non-destructive, because the wizard route only
writes the config file and the schema installer is gated on an empty database —
a before/after fingerprint of the table list, row counts, person rows, admin
password hash and schema version came back byte-identical.

Two caveats remain. A cross-version upgrade has not been tested, since 7.8.0 is
the first release with images and there is nothing newer to upgrade to. And
`read_only: true` silently breaks the setup wizard: the route returns HTTP 200
without checking whether `file_put_contents` succeeded, so the operator loops
back to `/setup` with no error shown.

## Publishing

Configure `DOCKERHUB_USERNAME` and `DOCKERHUB_TOKEN` in GitHub Actions secrets.
Use a dedicated publishing identity with the narrowest repository write access
available for the DockerHub account/plan. Do not grant delete or organization
administration access. A personal token's Read/Write scope alone is not a claim
of per-repository restriction.

The workflow:

1. Rejects drafts, prereleases and unsupported version tags.
2. Downloads `ChurchCRM-<version>.zip` from the exact triggering release.
3. Checks its GitHub asset SHA-256 digest, then validates and extracts the
   `churchcrm/` wrapper while preserving `.htaccess`. This verifies transport and
   asset consistency, not independent producer authenticity.
4. Builds the explicit `prod` target for amd64 and arm64, with SBOM and provenance.
5. Publishes version tags and conditionally the stable latest tags.

A release published by a workflow using `GITHUB_TOKEN` does not trigger another
release workflow. For that path, or a retry, use **Build & Push Docker Images →
Run workflow** with the existing stable `release_tag`. Run a ref containing these
Dockerfiles and workflow helpers. Human publication of a draft triggers the
normal release event. Workflow runs are serialized; GitHub concurrency retains
only one pending run, so rerun any displaced release explicitly.

PHP image references pin PHP 8.4.27, Debian Trixie and the multi-platform digest.
Update both the tag and digest together, keep builder and runtime references
identical, and rerun production image tests. The `PHP_IMAGE` build argument must
remain a compatible PHP 8.4+ image of the same server flavor and Debian suite.
Pinning does not replace regular security updates. Debian packages are still
resolved from live repositories, so builds are not bit-for-bit reproducible.

## Validation

```bash
python3 docker/test-release-context.py
python3 docker/prepare-release-context.py ChurchCRM-7.7.0.zip build-context
mkdir -p build-context/apache
cp docker/apache/default.conf build-context/apache/default.conf
docker build --target prod -f docker/Dockerfile.churchcrm-apache-php8 -t churchcrm-check:apache build-context
docker build --target prod -f docker/Dockerfile.churchcrm-fpm-php8 -t churchcrm-check:fpm build-context
bash docker/test-production-image.sh churchcrm-check:apache apache
bash docker/test-production-image.sh churchcrm-check:fpm fpm
```

The PR validation workflow repeats these builds on amd64 without registry
credentials. Smoke tests check non-root startup under dropped capabilities,
extensions, compiler absence and Apache's first HTTP response. They do not cover
database setup, FPM request routing, uploads, restoration or application upgrades.
