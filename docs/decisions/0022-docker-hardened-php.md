# Evaluate Docker Hardened Images for production PHP

[Issue #12021](https://github.com/ChurchCRM/CRM/issues/12021) follows the PHP 8.5 move. The published Apache and FPM Dockerfiles already default to PHP 8.5. Evaluate Debian 13 PHP 8.5 FPM as the first production candidate and keep the Apache tag available for single-container deployments during the evaluation.

## Production layout

Docker lists `dhi.io/php:8.5-debian13-fpm` with a direct `php-fpm` entrypoint, UID 65532, `/var/www/html` as the working directory, and FastCGI on port 9000. Pair it with a separate HTTP server and keep FastCGI on the Compose network. The [nginx example](../../docker/examples/docker-compose.nginx.yaml) provides routing to start from. Build both services from the same release artifact so versions match and a recreated container picks up updated code. Share uploaded assets explicitly. Persist the database, configuration, and photos independently of the release code. [Docker PHP image specifications](https://hub.docker.com/hardened-images/catalog/dhi/php/images/php%2Fdebian-13%2F8.5-fpm/sha256-dfde90381615a56d35315db2f68f5e319c5bdd477d784e0a116859690d3d1280/specifications)

The Apache production Compose file uses HTTP port 8080, a `curl` health check, and Apache access rules. A migration of that deployment needs an HTTP health check on the new web service and equivalent routing and access rules, including at subdirectory installs. It also needs an upgrade path for existing config and photo volumes, whose contents may be owned by UID 33. Verify those behaviors before changing the default Compose deployment or retiring the Apache image.

## Runtime compatibility

- The current production Dockerfiles install Debian packages, compile extensions with `docker-php-ext-*`, edit PHP configuration with shell tools, and set ownership for UID 33. Use a matching DHI development stage for build-time work and copy the resulting artifacts with explicit ownership. Verify the DHI PHP extension inventory, ABI and dynamically linked dependencies before choosing which extensions and libraries to add. Use the current [image runtime checks](../../tests/docker/test-production-image.js) as the baseline for the extension list and verify locale availability as well.
- The hardened FPM runtime has no shell or package manager. Keep the direct entrypoint. Replace shell and PHP CLI assumptions in the production smoke test with container metadata checks and HTTP requests through the web server. Check that PHP receives `CHURCHCRM_INSTALL_SOURCE` through FPM, including an explicit override.
- Setup exclusively creates `Include/Config.php` beside its template. Give the FPM user write access to the `Include` directory during setup and verify that the generated config survives container replacement. Keep PHP source private to FPM where possible. The web server needs its own readable static files and read access to any shared photos it serves. Verify a new install, login, locale rendering, photo upload and retrieval, and upgrade from the existing deployment. Test published `linux/amd64` and `linux/arm64` builds.

## Registry access and rollout

Local builders need `docker login dhi.io` with a Docker account or read token. Anonymous manifest inspection returned HTTP 401. Pulling the finished ChurchCRM image from its publication registry continues to use that registry's credentials and access rules. [Docker authentication guidance](https://docs.docker.com/dhi/how-to/use/)

Use DHI authentication for the production candidate in a trusted GitHub Actions workflow. Verify whether the existing Docker Hub release credential has pull access to `dhi.io`, or provision a read-only Docker token or Docker organization OIDC connection there. Run the authenticated image build and smoke tests before enabling release publication. Preserve the existing secret-free checks for fork PRs. The DHI base attestations describe the base image. Assess ChurchCRM's own image and attestations as part of validating the release.

Start with the production FPM candidate. Keep FrankenPHP on `dunglas/frankenphp` because Docker's catalog has no equivalent bundled Caddy and PHP runtime. Keep the dev/test stack on the existing bases for public builds and its shell, Composer, Node and Xdebug workflows. Run the setup and upgrade checks against the production candidate before adoption.
