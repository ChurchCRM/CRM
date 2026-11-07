# OpenAPI generator

Build tooling for the OpenAPI specs. The annotation recipe, tags, and regenerate steps are in [`api-development.md`](../../.agents/skills/churchcrm/api-development.md#openapi-documentation).

```
docs/openapi/
├── generate.php               # Generator (swagger-php v6 with ChurchCRMDocBlockAnalyser)
├── openapi-public-info.php    # Public API metadata and tags
├── openapi-private-info.php   # Private API metadata, security scheme and tags
└── generated/                 # public-api.yaml, private-api.yaml: committed by CI on master
```

`generate.php` is a development tool and does not ship with production code. Each spec has one server, the install root (`{scheme}://{host}{basePath}`), and every path is a site path. Generation fails when a documented path is not a Slim route.

`docs.churchcrm.io` reads `generated/` from the tag named in that repo's `crm-release.json`. It does not read `master`. `info.version` is the version of the tree that was scanned.
