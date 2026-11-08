<?php

declare(strict_types=1);

/**
 * Turns annotated paths into site paths and checks them against Slim routes.
 *
 * Each spec has one server, the install root. A path in the spec is the path
 * on that install: /api/person/{id}, /admin/api/user/{id}/password/reset.
 * Annotations stay relative to the Slim app that registers the route.
 *
 * @license MIT
 */

use OpenApi\Annotations\AbstractAnnotation;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\PathItem;
use OpenApi\Undefined;

final class OpenApiPaths
{
    /** Path fragment inside src/ => Slim base path for that app. */
    private const MOUNTS = [
        '/api/routes/' => '/api',
        '/admin/routes/api/' => '/admin',
        '/finance/routes/api/' => '/finance',
        '/kiosk/routes/api/' => '/kiosk',
        '/plugins/routes/api/' => '/plugins',
    ];

    private const METHODS = [
        'get' => 'GET',
        'post' => 'POST',
        'put' => 'PUT',
        'patch' => 'PATCH',
        'delete' => 'DELETE',
        'options' => 'OPTIONS',
        'head' => 'HEAD',
    ];

    public static function sitePath(string $annotated, string $mount): string
    {
        $path = '/' . ltrim($annotated, '/');
        if ($path === $mount || str_starts_with($path, $mount . '/')) {
            return $path;
        }

        return $mount . $path;
    }

    public static function mountFor(string $filename): string
    {
        $normalized = str_replace('\\', '/', $filename);
        foreach (self::MOUNTS as $fragment => $mount) {
            if (str_contains($normalized, $fragment)) {
                return $mount;
            }
        }

        throw new RuntimeException("No API mount for $filename");
    }

    /**
     * Compare paths ignoring parameter names and Slim placeholder patterns.
     */
    public static function pattern(string $path): string
    {
        $normalized = '';
        $length = strlen($path);
        for ($i = 0; $i < $length; $i++) {
            if ($path[$i] !== '{') {
                $normalized .= $path[$i];
                continue;
            }
            $depth = 1;
            $i++;
            while ($i < $length && $depth > 0) {
                if ($path[$i] === '{') {
                    $depth++;
                } elseif ($path[$i] === '}') {
                    $depth--;
                }
                $i++;
            }
            $i--;
            $normalized .= '{}';
        }

        return $normalized;
    }

    public static function rewrite(OpenApi $openapi): void
    {
        if (Undefined::isDefault($openapi->paths)) {
            return;
        }

        $items = [];
        foreach ($openapi->paths as $item) {
            if (!$item instanceof PathItem) {
                continue;
            }
            $grouped = [];
            foreach (self::METHODS as $property => $http) {
                $operation = $item->{$property};
                if (!$operation instanceof Operation) {
                    continue;
                }
                $site = self::sitePath((string) $item->path, self::mountFor(self::filename($operation)));
                $grouped[$site][$property] = $operation;
            }

            foreach ($grouped as $site => $operations) {
                if (!isset($items[$site]) && count($grouped) === 1) {
                    $item->path = $site;
                    $items[$site] = $item;
                    continue;
                }
                if (!isset($items[$site])) {
                    $items[$site] = new PathItem(['path' => $site]);
                }
                foreach ($operations as $property => $operation) {
                    $existing = $items[$site]->{$property};
                    if ($existing instanceof Operation) {
                        throw new RuntimeException("Duplicate $property $site");
                    }
                    $items[$site]->{$property} = $operation;
                }
            }
        }

        $openapi->paths = array_values($items);
    }

    /**
     * @param string[] $files Route files that were scanned
     */
    public static function assertMatchesRoutes(OpenApi $openapi, array $files): void
    {
        $routes = [];
        foreach ($files as $file) {
            try {
                $mount = self::mountFor($file);
            } catch (RuntimeException) {
                continue;
            }
            $source = file_get_contents($file);
            if ($source === false) {
                throw new RuntimeException("Cannot read $file");
            }
            $routes += self::routesFromSource($source, self::join($mount, self::requiredPrefix($file)));
        }

        $missing = [];
        if (!Undefined::isDefault($openapi->paths)) {
            foreach ($openapi->paths as $item) {
                if (!$item instanceof PathItem) {
                    continue;
                }
                foreach (self::METHODS as $property => $http) {
                    if (!$item->{$property} instanceof Operation) {
                        continue;
                    }
                    $key = $http . ' ' . self::pattern((string) $item->path);
                    if (!isset($routes[$key])) {
                        $missing[] = $http . ' ' . $item->path;
                    }
                }
            }
        }

        if ($missing !== []) {
            throw new RuntimeException("Documented paths with no Slim route:\n" . implode("\n", $missing));
        }
    }

    /**
     * @return array<string, true> "GET /admin/api/user/{}" => true
     */
    public static function routesFromSource(string $source, string $mount): array
    {
        $tokens = token_get_all($source);
        $routes = [];
        $prefixes = [''];
        $groupBrace = [];
        $brace = 0;
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === '{') {
                $brace++;
                continue;
            }
            if ($token === '}') {
                $brace--;
                while ($groupBrace !== [] && $groupBrace[array_key_last($groupBrace)] === $brace) {
                    array_pop($groupBrace);
                    array_pop($prefixes);
                }
                continue;
            }
            if (!is_array($token) || $token[0] !== T_OBJECT_OPERATOR) {
                continue;
            }

            $methodIndex = self::skipWhitespace($tokens, $i + 1);
            if ($methodIndex === null || !is_array($tokens[$methodIndex]) || $tokens[$methodIndex][0] !== T_STRING) {
                continue;
            }
            $method = strtolower($tokens[$methodIndex][1]);
            if (!in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'group'], true)) {
                continue;
            }

            $paren = self::skipWhitespace($tokens, $methodIndex + 1);
            if ($paren === null || $tokens[$paren] !== '(') {
                continue;
            }
            $pathIndex = self::skipWhitespace($tokens, $paren + 1);
            if ($pathIndex === null || !is_array($tokens[$pathIndex]) || $tokens[$pathIndex][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $literal = $tokens[$pathIndex][1];
            $path = stripcslashes(substr($literal, 1, -1));
            $prefix = $prefixes[array_key_last($prefixes)];
            if ($method === 'group') {
                $prefixes[] = self::join($prefix, $path);
                $groupBrace[] = $brace;
                continue;
            }

            $full = self::join($mount, self::join($prefix, $path));
            $routes[strtoupper($method) . ' ' . self::pattern($full)] = true;
        }

        return $routes;
    }

    /**
     * Prefix added by a module index.php that requires this route file inside a group.
     * Plugin routes are registered as $app->group('/api', ...) { require management.php }.
     */
    private static function requiredPrefix(string $routeFile): string
    {
        $dir = dirname($routeFile);
        $base = basename($routeFile);
        for ($depth = 0; $depth < 4; $depth++) {
            $index = $dir . '/index.php';
            if (is_file($index)) {
                $source = file_get_contents($index);
                if ($source !== false) {
                    $prefix = self::prefixWrappingRequire($source, $base);
                    if ($prefix !== null) {
                        return $prefix;
                    }
                }
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return '';
    }

    private static function prefixWrappingRequire(string $source, string $basename): ?string
    {
        $tokens = token_get_all($source);
        $prefixes = [''];
        $groupBrace = [];
        $brace = 0;
        $count = count($tokens);
        $found = null;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token === '{') {
                $brace++;
                continue;
            }
            if ($token === '}') {
                $brace--;
                while ($groupBrace !== [] && $groupBrace[array_key_last($groupBrace)] === $brace) {
                    array_pop($groupBrace);
                    array_pop($prefixes);
                }
                continue;
            }
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_REQUIRE || $token[0] === T_REQUIRE_ONCE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $part = $tokens[$j];
                    if ($part === ';') {
                        break;
                    }
                    if (is_array($part) && $part[0] === T_CONSTANT_ENCAPSED_STRING && str_contains($part[1], $basename)) {
                        $found = $prefixes[array_key_last($prefixes)];
                        break;
                    }
                }
                continue;
            }
            if ($token[0] !== T_OBJECT_OPERATOR) {
                continue;
            }
            $methodIndex = self::skipWhitespace($tokens, $i + 1);
            if ($methodIndex === null || !is_array($tokens[$methodIndex]) || $tokens[$methodIndex][0] !== T_STRING) {
                continue;
            }
            if (strtolower($tokens[$methodIndex][1]) !== 'group') {
                continue;
            }
            $paren = self::skipWhitespace($tokens, $methodIndex + 1);
            if ($paren === null || $tokens[$paren] !== '(') {
                continue;
            }
            $pathIndex = self::skipWhitespace($tokens, $paren + 1);
            if ($pathIndex === null || !is_array($tokens[$pathIndex]) || $tokens[$pathIndex][0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }
            $literal = $tokens[$pathIndex][1];
            $prefixes[] = self::join($prefixes[array_key_last($prefixes)], stripcslashes(substr($literal, 1, -1)));
            $groupBrace[] = $brace;
        }

        return $found;
    }

    private static function filename(AbstractAnnotation $annotation): string
    {
        $context = $annotation->_context;
        while ($context !== null) {
            if (is_string($context->filename) && $context->filename !== '') {
                return $context->filename;
            }
            $context = $context->parent;
        }

        throw new RuntimeException('OpenAPI operation has no source file');
    }

    private static function join(string $prefix, string $path): string
    {
        if ($path === '') {
            return $prefix === '' ? '/' : $prefix;
        }
        if ($prefix === '' || $prefix === '/') {
            return '/' . ltrim($path, '/');
        }
        if ($path === '/') {
            return rtrim($prefix, '/') . '/';
        }

        return rtrim($prefix, '/') . '/' . ltrim($path, '/');
    }

    /**
     * @param list<array|string> $tokens
     */
    private static function skipWhitespace(array $tokens, int $index): ?int
    {
        $count = count($tokens);
        while ($index < $count && is_array($tokens[$index]) && $tokens[$index][0] === T_WHITESPACE) {
            $index++;
        }

        return $index < $count ? $index : null;
    }
}
