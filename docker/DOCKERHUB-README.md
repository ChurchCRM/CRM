# ChurchCRM

[ChurchCRM](https://churchcrm.io) is free, open-source church management software: people and families, groups, events and attendance, giving and financial tracking, volunteers, and reports. It runs in 49 languages.

This image is the current ChurchCRM release, built from [ChurchCRM/CRM](https://github.com/ChurchCRM/CRM) on every release.

## Tags

| Tag | Contents |
| --- | --- |
| `latest`, `latest-php8-apache` | Current release, PHP 8 + Apache |
| `latest-php8-fpm` | Current release, PHP 8 + PHP-FPM |
| `<version>-php8-apache`, `<version>-php8-fpm` | A pinned release (e.g. `7.8.4-php8-apache`) |

`latest` always tracks the newest release. Pin a version tag if you don't want to move on release day.

## Quick start

The supported way to run it is the compose stack in the CRM repo:

1. Copy `docker/production/` from [ChurchCRM/CRM](https://github.com/ChurchCRM/CRM) somewhere of your own.
2. Copy `.env.example` to `.env` and fill it in.
3. `docker compose up -d`, then open `http://127.0.0.1:8080` and complete the setup wizard.

That stack runs MariaDB 11.4.5 in its own container (reachable only on the compose network, never from the host) and ChurchCRM on Apache, which listens on port **8080** inside the image — not 80.

Full instructions, backup and upgrade procedure: https://docs.churchcrm.io/installation/docker

## Notes

- The image ships no database — bring MariaDB/MySQL, as the compose stack does.
- Keep `Include/Config.php` across container replacements (see the comments in `docker/production/compose.yaml`) so a rebuild doesn't send you back to setup.
- The images are for evaluation and self-hosted installs. For anything public, put a TLS reverse proxy in front — the compose stack binds loopback only by default.

## Links

- Website: https://churchcrm.io
- Documentation: https://docs.churchcrm.io
- Source & issues: https://github.com/ChurchCRM/CRM
- Docker install guide: https://docs.churchcrm.io/installation/docker
