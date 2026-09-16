# Optional DDEV Environment

This community-requested configuration provides an alternative to ChurchCRM's primary local-tool and Docker test workflow. Maintainers do not use DDEV as their primary environment.

## Requirements

- Docker
- DDEV 1.24.8 or newer

## Setup

From the repository root:

```bash
ddev start
ddev setup-churchcrm
```

The first command starts Apache, PHP 8.4, MariaDB 10.11, and Mailpit, installs Composer dependencies, imports the demo database when needed, and selects the DDEV-specific ChurchCRM configuration. The second installs Node.js dependencies and builds the frontend assets.

Open ChurchCRM with:

```bash
ddev launch
```

Sign in with `admin` / `changeme`. Open the test mailbox with `ddev mailpit`.

## Useful commands

```bash
ddev describe
ddev logs
ddev ssh
ddev mysql
ddev stop
```

Run `ddev setup-churchcrm` again after changing Node.js dependencies or when frontend assets need a clean rebuild.

If this optional environment stops working, open a GitHub issue and include the output of `ddev describe` and `ddev version`.
