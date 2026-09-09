<?php

namespace ChurchCRM\Plugin;

/** Reads migration resources without loading or executing any plugin PHP. */
final class PluginMigrationManifest
{
    private const MAX_FILE_BYTES = 2097152;
    private const MAX_MIGRATIONS = 100;

    /**
     * IDs are strictly increasing ASCII strings; the array order is authoritative.
     * File paths are relative to the plugin root, including SQL paths.
     *
     * @return list<array{id: string, file: string, checksum: string, sql: string}>
     */
    public static function read(PluginMetadata $plugin): array
    {
        $path = $plugin->getMigrations();
        if ($path === null) {
            return [];
        }
        // Leave room for plugin.{id}.quarantineReason in config_cfg.cfg_name (50).
        if ($plugin->getType() !== 'community'
            || !in_array('db.migrate', $plugin->getPermissions(), true)
            || strlen($plugin->getId()) > 26
            // Double/trailing hyphens would overlap another plugin's __ prefix.
            || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $plugin->getId())) {
            throw new PluginMigrationException('Migrations require a community plugin, a valid ID of at most 26 characters, and the db.migrate permission.');
        }

        try {
            $manifest = json_decode(self::readFile($plugin, $path, 'json'), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new PluginMigrationException('The plugin migration manifest is not valid JSON.', 0, $e);
        }
        if (!is_array($manifest) || array_keys($manifest) !== ['migrations']
            || !is_array($manifest['migrations']) || !array_is_list($manifest['migrations'])
            || count($manifest['migrations']) > self::MAX_MIGRATIONS) {
            throw new PluginMigrationException('The migration manifest must contain one migrations array with at most 100 entries.');
        }

        $result = [];
        $previous = '';
        $files = [];
        $totalBytes = 0;
        foreach ($manifest['migrations'] as $entry) {
            if (!is_array($entry) || count($entry) !== 2
                || !isset($entry['id'], $entry['file']) || !is_string($entry['id']) || !is_string($entry['file'])
                || !preg_match('/^[0-9]{4,14}_[a-z0-9_]{1,80}$/D', $entry['id'])
                || strcmp($previous, $entry['id']) >= 0 || isset($files[$entry['file']])) {
                throw new PluginMigrationException('Migration IDs must be unique and strictly increasing; each entry needs id and a unique file.');
            }
            $sql = self::readFile($plugin, $entry['file'], 'sql');
            $totalBytes += strlen($sql);
            if ($totalBytes > 8388608) {
                throw new PluginMigrationException('Migration SQL resources must total no more than 8 MiB.');
            }
            self::validateSql($plugin->getId(), $sql);
            $result[] = ['id' => $entry['id'], 'file' => $entry['file'], 'checksum' => hash('sha256', $sql), 'sql' => $sql];
            $previous = $entry['id'];
            $files[$entry['file']] = true;
        }

        return $result;
    }

    /** @param list<array{id: string, file: string, checksum: string, sql: string}> $migrations */
    public static function fingerprint(array $migrations): string
    {
        $resources = array_map(static fn (array $entry): array => [$entry['id'], $entry['file'], $entry['checksum']], $migrations);

        return hash('sha256', json_encode($resources, JSON_THROW_ON_ERROR));
    }

    private static function readFile(PluginMetadata $plugin, string $relative, string $extension): string
    {
        if (!preg_match('~^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_.-]+)*\.' . $extension . '$~D', $relative)
            || str_contains($relative, '..')) {
            throw new PluginMigrationException('Migration paths must be relative files inside the plugin directory, without traversal or hidden segments.');
        }
        $root = realpath($plugin->getPath());
        if ($root === false || is_link($plugin->getPath())) {
            throw new PluginMigrationException('The plugin migration directory must be a real directory.');
        }
        $candidate = $root;
        foreach (explode('/', $relative) as $segment) {
            if (str_starts_with($segment, '.')) {
                throw new PluginMigrationException('Hidden migration paths are not allowed.');
            }
            $candidate .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($candidate)) {
                throw new PluginMigrationException('Migration resources must not use symbolic links.');
            }
        }
        $resolved = realpath($candidate);
        if ($resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR) || !is_file($resolved)) {
            throw new PluginMigrationException('A migration resource is missing or outside its plugin directory.');
        }
        // Bound the actual read, not just a potentially stale filesize() result.
        $contents = file_get_contents($resolved, false, null, 0, self::MAX_FILE_BYTES + 1);
        if ($contents === false || strlen($contents) > self::MAX_FILE_BYTES || trim($contents) === '') {
            throw new PluginMigrationException('Migration resources must be nonempty files no larger than 2 MiB.');
        }

        return $contents;
    }

    /**
     * A deliberately small SQL surface: one CREATE TABLE or ALTER TABLE on an
     * owned table. This is defense in depth, not a sandbox for untrusted PHP/SQL.
     * SQL still requires maintainer review and a high-risk registry declaration.
     */
    private static function validateSql(string $pluginId, string $sql): void
    {
        $tokens = [];
        $length = strlen($sql);
        for ($offset = 0; $offset < $length;) {
            if (preg_match('/\G(?:\s+|--(?=\s)[^\r\n]*|\#[^\r\n]*|\/\*(?![!+]|[Mm]!)[^*]*(?:\*(?!\/)[^*]*)*\*\/)/', $sql, $match, 0, $offset)) {
                $offset += strlen($match[0]);
                continue;
            }
            if (preg_match('/\G(?:`(?:``|[^`])*`|\'(?:\'\'|\\\\.|[^\'\\\\])*\'|"(?:""|\\\\.|[^"\\\\])*"|[a-zA-Z_][a-zA-Z0-9_]*|[0-9]+|[(),;=.+*\/-])/', $sql, $match, 0, $offset) !== 1) {
                throw new PluginMigrationException('Unsupported SQL token, executable comment, or unterminated string in a migration.');
            }
            $token = $match[0];
            // Keep parsing identical under ANSI_QUOTES and NO_BACKSLASH_ESCAPES.
            // Use single-quoted literals with doubled quotes, without backslashes.
            if ($token[0] === '"' || str_contains($token, '\\')) {
                throw new PluginMigrationException('Use single-quoted SQL literals with doubled quotes and no backslashes.');
            }
            $tokens[] = $token;
            if (count($tokens) > 50000) {
                throw new PluginMigrationException('A migration statement must contain at most 50000 SQL tokens.');
            }
            $offset += strlen($token);
        }
        if (end($tokens) === ';') {
            array_pop($tokens);
        }
        if (in_array(';', $tokens, true)) {
            throw new PluginMigrationException('Each migration file must contain exactly one SQL statement.');
        }
        $words = array_map('strtoupper', $tokens);
        if (!in_array($words[0] ?? '', ['CREATE', 'ALTER'], true) || ($words[1] ?? '') !== 'TABLE') {
            throw new PluginMigrationException('Only CREATE TABLE and ALTER TABLE migrations are supported.');
        }
        $tableIndex = 2;
        if ($words[0] === 'CREATE' && array_slice($words, 2, 3) === ['IF', 'NOT', 'EXISTS']) {
            $tableIndex = 5;
        }
        $prefix = 'plugin_' . str_replace('-', '_', $pluginId) . '__';
        self::assertOwnedTable($tokens[$tableIndex] ?? '', $prefix);
        if (($tokens[$tableIndex + 1] ?? '') === '.') {
            throw new PluginMigrationException('Database-qualified migration targets are not allowed.');
        }
        foreach ($words as $i => $word) {
            if (in_array($word, ['SELECT', 'LIKE', 'RENAME', 'DIRECTORY', 'TABLESPACE', 'CONNECTION', 'UNION', 'PARTITION', 'IMPORT', 'DISCARD', 'EXCHANGE', 'LOAD_FILE', 'OUTFILE', 'INFILE', 'ENGINE_ATTRIBUTE', 'SECONDARY_ENGINE_ATTRIBUTE'], true)) {
                throw new PluginMigrationException('Migration SQL contains an unsupported operation. Use only reviewed changes to plugin-owned InnoDB tables.');
            }
            if ($word === 'REFERENCES') {
                self::assertOwnedTable($tokens[$i + 1] ?? '', $prefix);
                if (($tokens[$i + 2] ?? '') === '.') {
                    throw new PluginMigrationException('Database-qualified foreign keys are not allowed.');
                }
            }
            if ($word === 'ENGINE' && (($words[$i + 1] ?? '') !== '=' || ($words[$i + 2] ?? '') !== 'INNODB')) {
                throw new PluginMigrationException('Plugin migrations support only ENGINE=InnoDB.');
            }
        }
        if ($words[0] === 'CREATE' && !in_array('ENGINE', $words, true)) {
            throw new PluginMigrationException('CREATE TABLE migrations must explicitly specify ENGINE=InnoDB.');
        }
    }

    private static function assertOwnedTable(string $token, string $prefix): void
    {
        $name = trim($token, '`');
        if (!preg_match('/^' . preg_quote($prefix, '/') . '[a-z0-9_]+$/D', $name) || strlen($name) > 64) {
            throw new PluginMigrationException('Migration tables and foreign keys must use the plugin-owned table prefix and be at most 64 characters.');
        }
    }
}
