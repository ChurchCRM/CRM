# OpenAPI Documentation Generator

This directory contains all tools and configuration for generating OpenAPI (Swagger) specifications for ChurchCRM APIs.

## Directory Structure

```
docs/openapi/
├── generate.php                    # Build tool (generates OpenAPI specs)
├── openapi-public-info.php        # Public API metadata and tags
├── openapi-private-info.php       # Private API metadata, security schemes, and tags
├── generated/                     # Output directory (generated YAML files)
│   ├── public-api.yaml           # Generated public API spec
│   └── private-api.yaml          # Generated private API spec
└── README.md                      # This file
```

## Usage

All generation commands should be run from the `src/` directory:

```bash
cd src
npm run openapi-public   # Generate public API spec
npm run openapi-private  # Generate private API spec
```

Or manually:

```bash
cd src
php ../docs/openapi/generate.php \
    ../docs/openapi/openapi-public-info.php \
    api/routes/public/ \
    --output ../docs/openapi/generated/public-api.yaml \
    --format yaml
```

## How It Works

1. **`generate.php`** - Build tool that:
   - Uses `ChurchCRM\Api\OpenAPI\ChurchCRMDocBlockAnalyser` to extract `@OA\` annotations from route files
   - Parses PHP docblocks using swagger-php v6
   - Generates OpenAPI YAML/JSON specifications

2. **`openapi-public-info.php`** - Defines:
   - Public API title, version, description
   - Contact information
   - License
   - Server URLs (production and local development)
   - Tag definitions for organizing endpoints

3. **`openapi-private-info.php`** - Defines:
   - Private API title, version, description
   - Security scheme (API key authentication)
   - Multiple server URLs (API, Admin, root)
   - Security tags and authentication details
   - Tag definitions for all authenticated endpoints

## Adding New API Endpoints

1. Create route handler in `src/api/routes/` (or `src/admin/routes/api/`, etc.)

2. Add `@OA\` docblock annotation:
```php
/**
 * @OA\Get(
 *     path="/public/echo",
 *     operationId="getEcho",
 *     summary="Health check",
 *     description="Returns echo response",
 *     tags={"Utility"},
 *     @OA\Response(
 *         response=200,
 *         description="Echo response",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="echo")
 *         )
 *     )
 * )
 */
function getEcho(Request $request, Response $response): Response
{
    return SlimUtils::renderJSON($response, ['message' => 'echo']);
}
```

3. Regenerate specs:
```bash
cd src
npm run openapi-public
npm run openapi-private
```

## Output Location

Generated specifications are saved to:
- `docs/openapi/generated/public-api.yaml` - Public API specification
- `docs/openapi/generated/private-api.yaml` - Private API specification

These files are committed to version control and served via the documentation site.

## Documentation Links

- **swagger-php**: https://github.com/zircote/swagger-php
- **OpenAPI Specification**: https://spec.openapis.org/
- **ChurchCRM API Analyzer**: `src/ChurchCRM/Api/OpenAPI/ChurchCRMDocBlockAnalyser.php`

## Notes

- This directory is part of the repository but does **not ship with production code**
- Only `docs/openapi/generated/` files are used by the documentation/API consumers
- The `generate.php` script is a build tool (development only)
- See `.gitignore` rules for which files are tracked
