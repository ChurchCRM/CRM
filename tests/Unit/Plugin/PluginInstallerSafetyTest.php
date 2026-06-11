<?php

declare(strict_types=1);

namespace ChurchCRM\Tests\Unit\Plugin;

use ChurchCRM\Plugin\PluginInstaller;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PluginInstaller security controls.
 *
 * Covers the scenarios introduced/fixed by GHSA-37mf-vq43-5qp9:
 *   - .php files in plugin ZIPs ARE allowed (plugins are PHP apps)
 *   - dangerous PHP variants (.phar, .php3–.php8, etc.) are rejected
 *   - double-extension tricks (evil.php.png) are rejected
 *   - ZIP Slip paths are rejected
 *   - hidden files in ZIPs are rejected
 *   - community/.htaccess deny block is written on install
 *
 * Because extractAndValidate() and ensureCommunityHtaccess() are private
 * static methods, we invoke them via ReflectionMethod.
 *
 * @requires extension zip
 */
class PluginInstallerSafetyTest extends TestCase
{
    private const PLUGIN_ID = 'test-plugin';

    /** Temporary files and directories created during a test, cleaned up in tearDown. */
    private array $tmpPaths = [];

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Build a real ZIP archive on disk with the given files nested under
     * `{pluginId}/`. Returns the path to the zip file.
     *
     * @param array<string,string> $files  map of relative path → content
     */
    private function buildZip(string $pluginId, array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ccrm_test_');
        $this->tmpPaths[] = $tmp;

        $zip = new \ZipArchive();
        $result = $zip->open($tmp, \ZipArchive::OVERWRITE);
        self::assertSame(true, $result, 'ZipArchive::open() failed in test helper');

        foreach ($files as $name => $content) {
            $zip->addFromString($pluginId . '/' . $name, $content);
        }
        $zip->close();

        return $tmp;
    }

    /**
     * Build a ZIP that contains an entry with an arbitrary raw path (without
     * the plugin-id prefix) — used for ZIP Slip tests.
     *
     * @param array<string,string> $entries  raw entry name → content
     */
    private function buildRawZip(array $entries): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'ccrm_test_');
        $this->tmpPaths[] = $tmp;

        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return $tmp;
    }

    /**
     * Minimal valid plugin.json for a plugin with the given ID.
     */
    private function validManifest(string $pluginId = self::PLUGIN_ID): string
    {
        return json_encode([
            'id'          => $pluginId,
            'name'        => 'Test Plugin',
            'version'     => '1.0.0',
            'type'        => 'community',
            'description' => 'PHPUnit test fixture',
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Create a fresh temporary directory and register it for cleanup.
     */
    private function tmpDir(): string
    {
        $dir = sys_get_temp_dir() . '/ccrm_test_' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        $this->tmpPaths[] = $dir;

        return $dir;
    }

    /**
     * Call the private static PluginInstaller::extractAndValidate().
     *
     * @throws \RuntimeException  propagated from the method under test
     */
    private function callExtractAndValidate(
        string $zipPath,
        string $destDir,
        string $pluginId,
        ?string $expectedVersion = null,
    ): void {
        $method = new \ReflectionMethod(PluginInstaller::class, 'extractAndValidate');
        $method->setAccessible(true);
        $method->invoke(null, $zipPath, $destDir, $pluginId, $expectedVersion);
    }

    /**
     * Call the private static PluginInstaller::ensureCommunityHtaccess().
     */
    private function callEnsureCommunityHtaccess(string $pluginsPath): void
    {
        $method = new \ReflectionMethod(PluginInstaller::class, 'ensureCommunityHtaccess');
        $method->setAccessible(true);
        $method->invoke(null, $pluginsPath);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Teardown
    // ──────────────────────────────────────────────────────────────────────────

    protected function tearDown(): void
    {
        foreach (array_reverse($this->tmpPaths) as $path) {
            if (is_dir($path)) {
                $this->removeDir($path);
            } elseif (is_file($path)) {
                @unlink($path);
            }
        }
        $this->tmpPaths = [];
    }

    private function removeDir(string $dir): void
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Test 1: A valid plugin ZIP containing .php files is accepted.
     *
     * Regression test for GHSA-37mf-vq43-5qp9 — the original (broken) fix
     * blocked .php in ALLOWED_EXTENSIONS, which would have made this throw.
     */
    public function testValidPluginWithPhpFilesIsAccepted(): void
    {
        $zipPath = $this->buildZip(self::PLUGIN_ID, [
            'plugin.json'         => $this->validManifest(),
            'src/FooPlugin.php'   => '<?php // plugin bootstrap',
            'routes/routes.php'   => '<?php // slim routes',
            'views/dashboard.php' => '<?php // view template',
        ]);
        $destDir = $this->tmpDir();

        // Must not throw — .php is in ALLOWED_EXTENSIONS.
        $this->callExtractAndValidate($zipPath, $destDir, self::PLUGIN_ID);

        self::assertFileExists($destDir . '/' . self::PLUGIN_ID . '/plugin.json');
        self::assertFileExists($destDir . '/' . self::PLUGIN_ID . '/src/FooPlugin.php');
        self::assertFileExists($destDir . '/' . self::PLUGIN_ID . '/views/dashboard.php');
    }

    /**
     * Test 2: community/.htaccess deny block is written by ensureCommunityHtaccess().
     *
     * Verifies GHSA-37mf-vq43-5qp9 mitigation: even if someone deletes the
     * checked-in .htaccess, the installer recreates it on every install.
     */
    public function testEnsureCommunityHtaccessWritesDenyBlock(): void
    {
        $pluginsPath = $this->tmpDir();
        $communityDir = $pluginsPath . '/community';
        mkdir($communityDir, 0700, true);

        $htaccessPath = $communityDir . '/.htaccess';
        self::assertFileDoesNotExist($htaccessPath, 'Pre-condition: .htaccess must not exist yet');

        $this->callEnsureCommunityHtaccess($pluginsPath);

        self::assertFileExists($htaccessPath);
        $content = file_get_contents($htaccessPath);
        self::assertStringContainsString('Require all denied', $content);
        self::assertStringContainsString('FilesMatch', $content);
        self::assertStringContainsString('\.php', $content);
        self::assertStringContainsString('GHSA-37mf-vq43-5qp9', $content);
    }

    /**
     * Test 2b: ensureCommunityHtaccess() is idempotent — calling it twice
     * does not overwrite an existing .htaccess with different content.
     */
    public function testEnsureCommunityHtaccessIsIdempotent(): void
    {
        $pluginsPath = $this->tmpDir();
        $communityDir = $pluginsPath . '/community';
        mkdir($communityDir, 0700, true);

        $htaccessPath = $communityDir . '/.htaccess';
        file_put_contents($htaccessPath, '# custom content');

        $this->callEnsureCommunityHtaccess($pluginsPath);

        // File must not be overwritten when it already exists.
        self::assertSame('# custom content', file_get_contents($htaccessPath));
    }

    /**
     * Test 3: A .phar file inside the ZIP is rejected.
     *
     * .phar is in DENIED_EXTENSIONS regardless of ALLOWED_EXTENSIONS.
     */
    public function testPharFileInZipIsRejected(): void
    {
        $zipPath = $this->buildZip(self::PLUGIN_ID, [
            'plugin.json'   => $this->validManifest(),
            'src/evil.phar' => '<?php // phar payload',
        ]);
        $destDir = $this->tmpDir();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/disallowed extension/i');

        $this->callExtractAndValidate($zipPath, $destDir, self::PLUGIN_ID);
    }

    /**
     * Test 4: PHP version-specific extensions (.php3, .php5, .php8) are rejected.
     *
     * These are in DENIED_EXTENSIONS — no legitimate plugin ships them.
     *
     * @dataProvider dangerousPhpVariantProvider
     */
    public function testDangerousPhpVariantIsRejected(string $extension): void
    {
        $zipPath = $this->buildZip(self::PLUGIN_ID, [
            'plugin.json'             => $this->validManifest(),
            'src/evil.' . $extension  => '<?php // payload',
        ]);
        $destDir = $this->tmpDir();

        $this->expectException(\RuntimeException::class);

        $this->callExtractAndValidate($zipPath, $destDir, self::PLUGIN_ID);
    }

    /** @return array<string,array{string}> */
    public static function dangerousPhpVariantProvider(): array
    {
        return [
            'php3'  => ['php3'],
            'php4'  => ['php4'],
            'php5'  => ['php5'],
            'php6'  => ['php6'],
            'php7'  => ['php7'],
            'php8'  => ['php8'],
            'phtml' => ['phtml'],
            'pht'   => ['pht'],
        ];
    }

    /**
     * Test 5: Double-extension tricks are rejected.
     *
     * Files like evil.php.png or shell.php.jpg are caught by the
     * preg_match guard in assertAllowedExtension() — added as part of
     * the GHSA-37mf-vq43-5qp9 fix.
     *
     * @dataProvider doubleExtensionProvider
     */
    public function testDoubleExtensionTrickIsRejected(string $filename): void
    {
        $zipPath = $this->buildZip(self::PLUGIN_ID, [
            'plugin.json'         => $this->validManifest(),
            'src/' . $filename    => '<?php // webshell',
        ]);
        $destDir = $this->tmpDir();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/dangerous PHP extension in filename/i');

        $this->callExtractAndValidate($zipPath, $destDir, self::PLUGIN_ID);
    }

    /** @return array<string,array{string}> */
    public static function doubleExtensionProvider(): array
    {
        return [
            'php.png'   => ['evil.php.png'],
            'php.jpg'   => ['shell.php.jpg'],
            'php.gif'   => ['backdoor.php.gif'],
            'phar.png'  => ['payload.phar.png'],
            'phtml.svg' => ['exploit.phtml.svg'],
            'php8.css'  => ['stealth.php8.css'],
        ];
    }

    /**
     * Test 6: Plain .php files are ALLOWED (core regression test for GHSA fix).
     *
     * This is the most important test: it asserts that the fix did NOT break
     * community plugin installation. A plugin with multiple PHP class files
     * must extract successfully.
     */
    public function testPlainPhpFilesAreAllowedForCommunityPlugins(): void
    {
        $zipPath = $this->buildZip(self::PLUGIN_ID, [
            'plugin.json'                     => $this->validManifest(),
            'src/MainPlugin.php'              => '<?php class MainPlugin {}',
            'src/Service/NotificationSvc.php' => '<?php class NotificationSvc {}',
            'src/Model/Member.php'            => '<?php class Member {}',
            'routes/routes.php'               => '<?php // routes',
            'views/index.php'                 => '<?php // view',
            'README.md'                       => '# My Plugin',
        ]);
        $destDir = $this->tmpDir();

        // Must not throw — this is the normal community plugin shape.
        $this->callExtractAndValidate($zipPath, $destDir, self::PLUGIN_ID);

        self::assertFileExists($destDir . '/' . self::PLUGIN_ID . '/src/MainPlugin.php');
        self::assertFileExists($destDir . '/' . self::PLUGIN_ID . '/src/Service/NotificationSvc.php');
        self::assertFileExists($destDir . '/' . self::PLUGIN_ID . '/routes/routes.php');
    }

    /**
     * Test 7: ZIP Slip path traversal is rejected.
     *
     * An entry with `../` in its name must be caught by assertSafeZipEntry().
     */
    public function testZipSlipPathIsRejected(): void
    {
        // Build a raw zip where the traversal entry sits under the plugin
        // top-level dir so it passes the "one top-level directory" check
        // before hitting the path-safety check.
        $zipPath = $this->buildRawZip([
            self::PLUGIN_ID . '/plugin.json'           => $this->validManifest(),
            self::PLUGIN_ID . '/../../../etc/passwd'   => 'root:x:0:0:root:/root:/bin/bash',
        ]);
        $destDir = $this->tmpDir();

        $this->expectException(\RuntimeException::class);

        $this->callExtractAndValidate($zipPath, $destDir, self::PLUGIN_ID);
    }

    /**
     * Test 8: A hidden .htaccess file inside the ZIP is rejected.
     *
     * Only `.editorconfig` and `.gitattributes` are permitted hidden files.
     * An attacker-supplied `.htaccess` could override our community deny block.
     */
    public function testHiddenHtaccessInZipIsRejected(): void
    {
        $zipPath = $this->buildZip(self::PLUGIN_ID, [
            'plugin.json' => $this->validManifest(),
            '.htaccess'   => 'Options +ExecCGI',
        ]);
        $destDir = $this->tmpDir();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/hidden file/i');

        $this->callExtractAndValidate($zipPath, $destDir, self::PLUGIN_ID);
    }

    /**
     * Test 8b: Allowed dotfiles (.editorconfig, .gitattributes) are accepted.
     */
    public function testAllowedDotfilesAreNotRejected(): void
    {
        $zipPath = $this->buildZip(self::PLUGIN_ID, [
            'plugin.json'      => $this->validManifest(),
            'src/Plugin.php'   => '<?php class Plugin {}',
            '.editorconfig'    => 'root = true',
            '.gitattributes'   => '* text=auto',
        ]);
        $destDir = $this->tmpDir();

        // Must not throw.
        $this->callExtractAndValidate($zipPath, $destDir, self::PLUGIN_ID);

        self::assertFileExists($destDir . '/' . self::PLUGIN_ID . '/.editorconfig');
        self::assertFileExists($destDir . '/' . self::PLUGIN_ID . '/.gitattributes');
    }
}
