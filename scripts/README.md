# ChurchCRM Build & Utility Scripts

This folder contains Node.js and shell scripts used for building, packaging, and maintaining ChurchCRM.

## Scripts Overview

| Script | Purpose | Usage |
|--------|---------|-------|
| `setup-dev-environment.sh` | Automated development setup | `./scripts/setup-dev-environment.sh` |
| `generate-signatures-node.js` | Generate file integrity checksums | `npm run build:signatures` |
| `validate-php-syntax.js` | Validate PHP syntax in build | `npm run build:php:validate` |
| `package-release.js` | Create release ZIP package | `npm run package` |
| `locale-check.js` | Lint locale files for issues | `npm run locale:lint` |
| `startNewRelease.js` | Version bump for releases | Used by maintainers |
| `create-plugin.php` | Scaffold a new community plugin from `examples/community-plugin-hello-world/` | `php scripts/create-plugin.php <kebab-id> [--author="Name"]` |
| `plugin-scan.php` | Self-audit a community plugin directory against the maintainer review checklist | `php scripts/plugin-scan.php [--json] <plugin-dir>` |

---

## Script Details

### setup-dev-environment.sh

**Automated setup for new developers.** Checks prerequisites, installs dependencies, and starts Docker containers.

```bash
./scripts/setup-dev-environment.sh
```

**What it does:**
1. Checks for Docker and npm
2. Runs `npm ci` to install dependencies
3. Initializes Git LFS (if available)
4. Starts Docker dev containers
5. Builds ChurchCRM inside the container

**Prerequisites:**
- Docker installed and running
- Node.js/npm installed
- Git (with optional Git LFS)

---

### generate-signatures-node.js

**Generates SHA-1 signatures for all source files.** Used for integrity verification.

```bash
npm run build:signatures
```

**Output:** `src/admin/data/signatures.json`

**What it does:**
- Walks through all files in `src/`
- Generates SHA-1 hash for each file
- Excludes vendor examples, tests, docs
- Creates JSON manifest with version and file hashes

---

### validate-php-syntax.js

**Validates PHP syntax for all files in the signatures manifest.**

```bash
npm run build:php:validate
```

**What it does:**
- Reads `signatures.json` for file list
- Runs `php -l` on each PHP file
- Skips vendor files (validated by Composer)
- Reports syntax errors with file and line

**Requires:** PHP CLI available in PATH

---

### package-release.js

**Creates a release ZIP package** for distribution.

```bash
npm run package
```

**Output:** `temp/ChurchCRM-{version}.zip`

**What it does:**
- Reads version from `package.json`
- Archives `src/` directory
- Excludes development files (.git, tests, docs)
- Creates optimized ZIP with maximum compression

---

### locale-check.js

**Lints locale files for common issues** like improper string formatting.

```bash
npm run locale:lint
```

**What it checks:**
- `gettext()` calls with colons (potential issues)
- `i18next.t()` patterns
- Malformed `msgid` entries in .po files

---

### startNewRelease.js

**Version management for releases.** Used by maintainers to bump version numbers.

```bash
node scripts/startNewRelease.js <new-version>
```

**What it updates:**
- `package.json` version
- Database upgrade configuration
- Creates git tag

**Note:** This script is for maintainers only. Contributors should not modify version numbers.

---

### create-plugin.php

**Scaffold a new community plugin.** Copies
`examples/community-plugin-hello-world/` into
`src/plugins/community/<kebab-id>/`, rewriting plugin id, PHP
namespace, and main class name so the result is immediately runnable.

```bash
php scripts/create-plugin.php my-plugin --author="Jane Doe"
```

**Output:** a complete plugin skeleton with `plugin.json`,
`src/{PascalId}Plugin.php`, and `locale/i18n/en_US.json`. If the id
already ends in `-plugin`, the trailing suffix is stripped so the
main class doesn't end up named `FooPluginPlugin`.

**Next step:** run `plugin-scan.php` against the new directory to
confirm it passes the self-audit before enabling it from Admin → Plugins.

---

### plugin-scan.php

**Community plugin self-audit.** Runs the same checklist ChurchCRM
maintainers use during the plugin security review, against a plugin
directory on disk. Intended for authors to run against their own
plugin BEFORE opening a pull request against
`src/plugins/approved-plugins.json`.

```bash
php scripts/plugin-scan.php src/plugins/community/my-plugin
php scripts/plugin-scan.php --json src/plugins/community/my-plugin | jq
```

**What it checks:**
- `plugin.json` exists, is valid JSON, has required fields, uses
  kebab-case id, declares `type: "community"`, and the declared
  `mainClass` + `routesFile` resolve on disk.
- Every file has an allowed extension. `.phar`, `.sh`, `.exe`,
  `.so`, `.dll`, `.phtml`, etc. are rejected.
- No hidden files (other than `.editorconfig` / `.gitattributes`).
- PHP and JS sources are free of dangerous sinks (`eval`,
  `shell_exec`, `passthru`, `proc_open`, `system`, `pcntl_exec`,
  `extract($_POST)`, `parse_str($_POST)`, `preg_replace(.../e)`,
  `unserialize`, `base64_decode` of bundled blobs, etc.).
- Plugin code uses `dgettext()` for translations, not plain
  `gettext()` / `_()` — that would hit the core `messages` textdomain.
- Every outbound hostname referenced in source is collected and
  listed so the author can confirm each one is named in `riskSummary`
  and covered by `network.outbound`.
- Every literal `file_put_contents()` target is listed so the author
  can confirm writes stay inside the plugin directory.

**Exit codes:** 0 (no errors), 1 (at least one error), 2 (invalid
invocation).

---

## Related Documentation

- [Development Guide](https://github.com/ChurchCRM/CRM/wiki/Development) - Full development setup
- [npm Scripts Reference](https://github.com/ChurchCRM/CRM/wiki/Development#key-npm-scripts) - All available npm commands
- [Documentation](https://docs.churchcrm.io/contributing) - How to contribute
- [Testing Guide](https://github.com/ChurchCRM/CRM/wiki/Testing) - Cypress testing documentation

---

## Adding New Scripts

When adding new scripts:

1. **Use Node.js** for cross-platform compatibility
2. **Add npm script** in `package.json` for easy invocation
3. **Document here** with purpose and usage
4. **Update wiki** if it affects developer workflow
5. **Include error handling** with clear error messages
