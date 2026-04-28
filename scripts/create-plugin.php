<?php

/**
 * scripts/create-plugin.php — community plugin scaffolder.
 *
 * Usage:
 *   php scripts/create-plugin.php <kebab-id> [--author="Your Name"]
 *
 * Clones https://github.com/ChurchCRM/community-plugin-hello-world into
 * a temporary directory, then copies it into
 * src/plugins/community/<kebab-id>/, rewriting plugin id, namespace,
 * and class names so the result is immediately runnable.
 *
 * Pairs with scripts/plugin-scan.php (run that after you code to
 * self-audit before opening an approved-plugins.json PR).
 *
 * This script lives outside the web app — it writes files into src/
 * and is meant to be run by a developer on their own workstation.
 * It is NEVER invoked from PHP-FPM / Apache.
 */

declare(strict_types=1);

(function (): void {
    $repoRoot = dirname(__DIR__);
    $communityRoot = $repoRoot . '/src/plugins/community';
    $templateRepo = 'https://github.com/ChurchCRM/community-plugin-hello-world';

    $argv = $_SERVER['argv'] ?? [];
    array_shift($argv); // drop the script name itself

    $pluginId = null;
    $author = 'Your Name';
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--author=')) {
            $author = substr($arg, strlen('--author='));
            continue;
        }
        if (str_starts_with($arg, '--')) {
            fail('Unknown option: ' . $arg);
        }
        if ($pluginId === null) {
            $pluginId = $arg;
        }
    }

    if ($pluginId === null) {
        usage();
        exit(1);
    }

    if (!preg_match('/^[a-z0-9][a-z0-9-]*$/', $pluginId)) {
        fail('Plugin id must be kebab-case: start with [a-z0-9], contain only [a-z0-9-].');
    }
    if ($pluginId === 'hello-world') {
        fail('Plugin id "hello-world" is reserved for the example template. Choose another.');
    }
    if ($pluginId === 'messages') {
        fail('Plugin id "messages" conflicts with the core gettext domain.');
    }

    $destDir = $communityRoot . '/' . $pluginId;
    if (is_dir($destDir)) {
        fail('Destination already exists: ' . $destDir);
    }

    // Clone the template repo to a temp directory.
    $tmpDir = sys_get_temp_dir() . '/ccrm-plugin-template-' . bin2hex(random_bytes(6));
    echo "Cloning template from {$templateRepo} ...\n";
    exec('git clone --depth 1 ' . escapeshellarg($templateRepo) . ' ' . escapeshellarg($tmpDir) . ' 2>&1', $cloneOutput, $cloneStatus);
    if ($cloneStatus !== 0) {
        fail("Could not clone template:\n" . implode("\n", $cloneOutput));
    }

    // Derive a PascalCase class name base from the kebab id. If the id
    // already ends with "-plugin", strip the suffix — otherwise the
    // main class would end up named FooPluginPlugin, which nobody
    // wants. The suffix "Plugin" is always appended after.
    $pascalId = kebabToPascal(preg_replace('/-plugin$/', '', $pluginId) ?? $pluginId);

    echo "Scaffolding new community plugin\n";
    echo "  id:     {$pluginId}\n";
    echo "  class:  {$pascalId}Plugin\n";
    echo "  path:   " . ltrim(str_replace($repoRoot, '', $destDir), '/') . "\n";
    echo "  author: {$author}\n";
    echo "\n";

    copyTemplate($tmpDir, $destDir, $pluginId, $pascalId, $author);
    recursiveDelete($tmpDir);

    // Rename the main class file from HelloWorldPlugin.php to {PascalId}Plugin.php.
    $oldMain = $destDir . '/src/HelloWorldPlugin.php';
    $newMain = $destDir . '/src/' . $pascalId . 'Plugin.php';
    if (is_file($oldMain)) {
        rename($oldMain, $newMain);
    }

    echo "✔ Scaffold complete.\n\n";
    echo "Next steps:\n";
    echo "  1. Edit {$newMain} and any settings/routes you need.\n";
    echo "  2. Run the self-scan:  php scripts/plugin-scan.php {$destDir}\n";
    echo "  3. Enable the plugin from Admin → Plugins once it passes the scan.\n";
    echo "  4. When you're ready to publish, follow\n";
    echo "     .agents/skills/churchcrm/plugin-create.md\n";
    echo "     (sections 6–8) to build a release zip and open a PR against\n";
    echo "     src/plugins/approved-plugins.json.\n";
})();

// ──────────────────────────────────────────────────────────────────

function usage(): void
{
    echo <<<USAGE
Usage: php scripts/create-plugin.php <kebab-id> [--author="Name"]

Example:
  php scripts/create-plugin.php my-plugin --author="Jane Doe"

This clones https://github.com/ChurchCRM/community-plugin-hello-world
into a temp directory, then copies it into
src/plugins/community/<kebab-id>/, rewriting the plugin id, PHP
namespace, and main class name so the result is runnable.

USAGE;
}

function fail(string $msg): void
{
    fwrite(STDERR, "create-plugin: " . $msg . PHP_EOL);
    exit(1);
}

function kebabToPascal(string $id): string
{
    return str_replace(' ', '', ucwords(str_replace('-', ' ', $id)));
}

function recursiveDelete(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

/**
 * Copy the example tree to $dest, rewriting identifiers as it goes.
 * Also generates a fresh README.md and locale fallback file.
 */
function copyTemplate(
    string $src,
    string $dest,
    string $pluginId,
    string $pascalId,
    string $author
): void {
    if (!mkdir($dest, 0755, true) && !is_dir($dest)) {
        fail('Could not create ' . $dest);
    }

    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iter as $item) {
        /** @var SplFileInfo $item */
        $rel = substr($item->getPathname(), strlen($src) + 1);
        $target = $dest . '/' . $rel;

        if ($item->isDir()) {
            if (!is_dir($target) && !mkdir($target, 0755, true) && !is_dir($target)) {
                fail('Could not create ' . $target);
            }
            continue;
        }

        $content = (string) file_get_contents($item->getPathname());
        $content = rewriteTemplate($content, $pluginId, $pascalId, $author);

        if (file_put_contents($target, $content) === false) {
            fail('Could not write ' . $target);
        }
    }

    // Overwrite README with a fresh one that does not reference the
    // example-template wording.
    file_put_contents(
        $dest . '/README.md',
        renderPluginReadme($pluginId, $pascalId, $author)
    );
}

function rewriteTemplate(
    string $content,
    string $pluginId,
    string $pascalId,
    string $author
): string {
    // Order matters — replace the longer strings first to avoid
    // accidentally matching a substring of a longer identifier.
    $replacements = [
        'ChurchCRM\\\\Plugins\\\\HelloWorld\\\\HelloWorldPlugin' => 'ChurchCRM\\\\Plugins\\\\' . $pascalId . '\\\\' . $pascalId . 'Plugin',
        'ChurchCRM\\Plugins\\HelloWorld\\HelloWorldPlugin' => 'ChurchCRM\\Plugins\\' . $pascalId . '\\' . $pascalId . 'Plugin',
        'namespace ChurchCRM\\Plugins\\HelloWorld;' => 'namespace ChurchCRM\\Plugins\\' . $pascalId . ';',
        'HelloWorldPlugin' => $pascalId . 'Plugin',
        "'hello-world'" => "'" . $pluginId . "'",
        '"hello-world"' => '"' . $pluginId . '"',
        'hello-world.mo' => $pluginId . '.mo',
        'hello-world"' => $pluginId . '"',
        'HelloWorld' => $pascalId,
        'Hello World' => ucwords(str_replace('-', ' ', $pluginId)),
        'helloworld.' => str_replace('-', '', $pluginId) . '.',
        'ChurchCRM Community' => $author,
    ];

    foreach ($replacements as $needle => $replacement) {
        $content = str_replace($needle, $replacement, $content);
    }

    return $content;
}

function renderPluginReadme(string $pluginId, string $pascalId, string $author): string
{
    $displayName = ucwords(str_replace('-', ' ', $pluginId));
    return <<<MD
# {$displayName}

Community plugin for [ChurchCRM](https://churchcrm.io), scaffolded
from [ChurchCRM/community-plugin-hello-world](https://github.com/ChurchCRM/community-plugin-hello-world).

## Quickstart

1. Edit `src/{$pascalId}Plugin.php` — replace the example hook handler
   with whatever your plugin actually needs to do.
2. Update `plugin.json` — set the `description`, add/remove settings,
   and list every `hooks.*` capability your plugin will need.
3. Run the self-scan before enabling the plugin:

   ```bash
   php scripts/plugin-scan.php src/plugins/community/{$pluginId}
   ```

4. Enable the plugin from **Admin → Plugins** in the ChurchCRM UI.
5. When you are ready to publish, see **Publishing your plugin** below.

## Rules of the road

- Use `\$this->getConfigValue('key')` / `\$this->setConfigValue('key', 'value')`
  to read or write your plugin's own config. Never touch another
  plugin's keys.
- Use `dgettext('{$pluginId}', 'string')` for PHP translations. Ship
  compiled `.mo` files under `locale/textdomain/{locale}/LC_MESSAGES/{$pluginId}.mo`.
- Use `window.CRM.plugins['{$pluginId}'].i18n[key]` for JS translations.
  Ship flat `key → string` maps at `locale/i18n/{locale}.json`.

## Publishing your plugin

1. Tag a release on GitHub with a zip artifact
   (GitHub release assets are ideal — immutable URLs, no CDN required).
2. Compute the SHA-256 of the zip: `sha256sum {$pluginId}-1.0.0.zip`
3. Open a PR against [ChurchCRM/CRM](https://github.com/ChurchCRM/CRM)
   that adds your entry to `src/plugins/approved-plugins.json`.
4. A maintainer will run the security scan checklist against your zip.
   Full details: <https://docs.churchcrm.io/administration/plugins/plugin-security-and-compliance>

## Documentation & support

| Resource | Link |
|----------|------|
| Plugin overview | <https://docs.churchcrm.io/administration/plugins/> |
| Installing community plugins | <https://docs.churchcrm.io/administration/plugins/installing-community-plugins> |
| Security & compliance | <https://docs.churchcrm.io/administration/plugins/plugin-security-and-compliance> |
| Localization guide | <https://docs.churchcrm.io/administration/plugins/plugin-localization> |
| Community Discord | <https://discord.gg/churchcrm> |

Author: {$author}
MD;
}
