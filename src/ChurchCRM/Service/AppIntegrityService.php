<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Prerequisite;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;
use ChurchCRM\Utils\PhpVersion;

class AppIntegrityService
{
    private static $IntegrityCheckDetails;

    private static function resolveDocumentRoot(): string
    {
        $documentRoot = SystemURLs::getDocumentRoot();
        if (is_string($documentRoot) && $documentRoot !== '') {
            return $documentRoot;
        }

        $setupDocRoot = $GLOBALS['CHURCHCRM_SETUP_DOC_ROOT'] ?? null;
        if (is_string($setupDocRoot) && $setupDocRoot !== '') {
            return $setupDocRoot;
        }

        return dirname(__DIR__, 2);
    }

    private static function getIntegrityCheckData()
    {
        $logger = LoggerUtils::getAppLogger();
        if (AppIntegrityService::$IntegrityCheckDetails !== null) {
            $logger->debug('Integrity check results already cached in memory; not recalculating');

            return AppIntegrityService::$IntegrityCheckDetails;
        }

        $logger->debug('Running integrity check');

        // Always run verification fresh - don't use persistent cache files
        $verificationResult = AppIntegrityService::verifyApplicationIntegrity();
        AppIntegrityService::$IntegrityCheckDetails = (object) $verificationResult;

        return AppIntegrityService::$IntegrityCheckDetails;
    }

    public static function getIntegrityCheckStatus(): string
    {
        if (AppIntegrityService::getIntegrityCheckData()->status === 'failure') {
            return gettext('Failed');
        }

        return gettext('Passed');
    }

    public static function getIntegrityCheckMessage(): string
    {
        $integrityData = AppIntegrityService::getIntegrityCheckData();

        if (AppIntegrityService::getIntegrityCheckStatus() === gettext('Passed')) {
            $integrityData->message = gettext('The previous integrity check passed. All system file hashes match the expected values.');
        }

        return $integrityData->message ?? '';
    }

    public static function getFilesFailingIntegrityCheck()
    {
        return AppIntegrityService::getIntegrityCheckData()->files ?? [];
    }

    public static function verifyApplicationIntegrity(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $documentRoot = AppIntegrityService::resolveDocumentRoot();
        $signatureFile = $documentRoot . '/admin/data/signatures.json';
        $signatureFailures = [];
        if (is_file($signatureFile)) {
            if (!is_readable($signatureFile)) {
                $logger->warning("Signature definition file is not readable: {signatureFile}", [
                    'signatureFile' => $signatureFile,
                ]);

                return [
                    'status'  => 'failure',
                    'message' => gettext('Signature definition file exists but is not readable. Check file permissions.'),
                    'files'   => [],
                ];
            }

            $logger->debug("Signature file found at: {signatureFile}", [
                'signatureFile' => $signatureFile,
            ]);

            try {
                $signatureFileContents = file_get_contents($signatureFile);
                MiscUtils::throwIfFailed($signatureFileContents);
                $signatureData = json_decode($signatureFileContents, null, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $e) {
                $logger->warning("Error decoding signature definition file: {signatureFile}", [
                    'signatureFile' => $signatureFile,
                    'exception' => $e,
                ]);

                return [
                    'status'  => 'failure',
                    'message' => gettext('Error decoding signature definition file'),
                ];
            }
            if (sha1(json_encode($signatureData->files, JSON_UNESCAPED_SLASHES)) === $signatureData->sha1) {
                foreach ($signatureData->files as $file) {
                    $currentFile = $documentRoot . '/' . $file->filename;
                    if (is_file($currentFile)) {
                        $actualHash = sha1_file($currentFile);
                        if ($actualHash !== $file->sha1) {
                            $logger->warning("File hash mismatch: {filename}. Expected: {expectedHash}; Got: {actualHash}", [
                                'filename' => $file->filename,
                                'expectedHash' => $file->sha1,
                                'actualHash' => $actualHash,
                            ]);
                                $signatureFailures[] = (string) $file->filename;
                        }
                    } else {
                        $logger->warning("File Missing: {filename}", [
                            'filename' => $file->filename,
                        ]);
                            $signatureFailures[] = (string) $file->filename;
                    }
                }
            } else {
                $logger->warning('Signature definition file signature failed validation');

                return [
                    'status'  => 'failure',
                    'message' => gettext('Signature definition file signature failed validation'),
                ];
            }
        } else {
            $logger->warning("Signature definition file not found at: {signatureFile}", [
                'signatureFile' => $signatureFile,
            ]);

            return [
                'status'  => 'failure',
                'message' => sprintf(
                    gettext('Signature definition file is missing at %s. Run the packaging task or deploy an official release to regenerate signatures.'),
                    $signatureFile
                ),
                'files'   => [],
            ];
        }

        if (count($signatureFailures) > 0) {
            return [
                'status'  => 'failure',
                'message' => gettext('One or more files failed signature validation'),
                'files'   => $signatureFailures,
            ];
        }

        return [
            'status'  => 'success',
            'message' => gettext('All system file signatures match the expected values.'),
            'files'   => [],
        ];
    }

    private static function verifyDirectoryWriteable(string $path): bool
    {
        $logger = LoggerUtils::getAppLogger();

        if (is_dir($path) && is_writable($path)) {
            return true;
        }

        $logger->warning("Directory is not writeable: {path}", [
            'path' => $path,
        ]);
        return  false;
    }

    /**
     * @return Prerequisite[]
     */
    public static function getApplicationPrerequisites(): array
    {
        $requiredPhp = PhpVersion::getRequiredPhpVersion();

        return [
            new Prerequisite('PHP ' . $requiredPhp . '+', fn (): bool => version_compare(PHP_VERSION, $requiredPhp, '>=')),
            new Prerequisite('PCRE and UTF-8 Support', fn (): bool => function_exists('preg_match') && @preg_match('/^.$/u', 'ñ') && @preg_match('/^\pL$/u', 'ñ')),
            new Prerequisite('Multibyte Encoding', fn (): bool => function_exists('mb_strlen')),
            new Prerequisite('PHP Phar', fn (): bool => class_exists('PharData')),
            new Prerequisite('PHP Session', fn (): bool => function_exists('session_start')),
            new Prerequisite('PHP XML', fn (): bool => class_exists('SimpleXMLElement')),
            new Prerequisite('PHP iconv', fn (): bool => function_exists('iconv')),
            new Prerequisite('Mod Rewrite or Equivalent', fn (): bool => AppIntegrityService::hasModRewrite()),
            new Prerequisite(
                'GD Library for image manipulation',
                fn (): bool =>
                    function_exists('imagecreatetruecolor') &&
                    function_exists('imagecreatefromstring') &&
                    function_exists('imagecopyresampled') &&
                    function_exists('imagepng')
            ),
            new Prerequisite('FileInfo Extension for image manipulation', fn (): bool => function_exists('finfo_open') || function_exists('mime_content_type')),
            new Prerequisite('cURL', fn (): bool => function_exists('curl_init')),
            new Prerequisite('locale gettext', fn (): bool => function_exists('bindtextdomain') && function_exists('gettext')),
            new Prerequisite('PHP Sodium', fn (): bool => function_exists('sodium_crypto_secretbox')),
            new Prerequisite('PHP ZipArchive', fn (): bool => class_exists('ZipArchive')),
            new Prerequisite('Mysqli Functions', fn (): bool => function_exists('mysqli_connect')),
        ];
    }

    /**
     * @return Prerequisite[]
     */
    public static function getFilesystemPrerequisites(): array
    {
        $documentRoot = AppIntegrityService::resolveDocumentRoot();

        return [
            new Prerequisite('Include/Config file is writeable', fn (): bool => AppIntegrityService::verifyDirectoryWriteable($documentRoot . '/Include/')),
            new Prerequisite('Images directory is writeable', fn (): bool => AppIntegrityService::verifyDirectoryWriteable($documentRoot . '/Images/')),
            new Prerequisite('Images directory is writeable - Family', fn (): bool => AppIntegrityService::verifyDirectoryWriteable($documentRoot . '/Images/Family')),
            new Prerequisite('Images directory is writeable - Person', fn (): bool => AppIntegrityService::verifyDirectoryWriteable($documentRoot . '/Images/Person')),
        ];
    }

    /**
     * @return Prerequisite[]
     */
    public static function getUnmetPrerequisites(): array
    {
        $allPrerequisites = array_merge(
            AppIntegrityService::getApplicationPrerequisites(),
            AppIntegrityService::getFilesystemPrerequisites()
        );

        return array_filter(
            $allPrerequisites,
            fn (Prerequisite $prereq): bool => !$prereq->isPrerequisiteMet()
        );
    }

    public static function arePrerequisitesMet(): bool
    {
        return count(AppIntegrityService::getUnmetPrerequisites()) === 0;
    }

    public static function hasModRewrite(): bool
    {
        $logger = LoggerUtils::getAppLogger();

        if (function_exists('curl_version')) {
            $ch = curl_init();

            // Security fix: Do NOT use HTTP_REFERER header as it's user-controlled (SSRF vulnerability)
            // Use SERVER_NAME instead of HTTP_HOST since HTTP_HOST is also user-controlled
            $request_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            if (isset($_SERVER['REQUEST_SCHEME'])) {
                $request_scheme = $_SERVER['REQUEST_SCHEME'];
            }
            $request_host = $_SERVER['SERVER_NAME'] ?? 'localhost';

            // Run a test against an URL we know does not exist to check for ModRewrite like functionality
            $rewrite_chk_url = $request_scheme . '://' . $request_host . SystemURLs::getRootPath() . '/INVALID';
            $logger->debug("Testing CURL loopback check to: $rewrite_chk_url");

            curl_setopt($ch, CURLOPT_URL, $rewrite_chk_url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);

            $result = curl_exec($ch);
            curl_close($ch);

            $isEnabled = preg_match('/^CRM:\s*(.*)$/mi', $result, $matches) === 1;

            $logger->debug('CURL loopback check header observed: ' . ($isEnabled ? 'true' : 'false'));
            return $isEnabled;
        }

        return false;
    }

    /**
     * Find files on the server that are not in the signatures.json manifest
     * These are potentially orphaned files that could be security risks
     *
     * signatures.json stores paths relative to src/ (e.g., "index.php", "vendor/...")
     * This method scans src/ and returns paths relative to document root (e.g., "src/index.php")
     *
     * @return array List of orphaned file paths relative to document root
     */
    public static function getOrphanedFiles(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $documentRoot = AppIntegrityService::resolveDocumentRoot();
        $signatureFile = $documentRoot . '/admin/data/signatures.json';
        $orphanedFiles = [];

        // Get list of files in signatures.json (paths are relative to src/)
        $validFiles = [];
        if (is_file($signatureFile) && is_readable($signatureFile)) {
            try {
                $signatureFileContents = file_get_contents($signatureFile);
                MiscUtils::throwIfFailed($signatureFileContents);
                $signatureData = json_decode($signatureFileContents, null, 512, JSON_THROW_ON_ERROR);
                if (isset($signatureData->files) && is_array($signatureData->files)) {
                    foreach ($signatureData->files as $file) {
                        $validFiles[$file->filename] = true;
                    }
                }
                $logger->debug('Loaded signatures for orphan detection', ['count' => count($validFiles)]);
            } catch (\Exception $e) {
                $logger->warning('Error reading signatures for orphan detection', ['exception' => $e]);
                return [];
            }
        } else {
            $logger->warning('Signature file not found or not readable', ['file' => $signatureFile]);
            return [];
        }

        // Scan src/ directory - signatures.json paths are relative to src/
        $srcPath = $documentRoot;
        if (is_dir($srcPath)) {
            $orphanedFiles = AppIntegrityService::scanDirectoryForOrphans($srcPath, $srcPath, $validFiles);
        }

        // Also scan for legacy avatar files (initials and gravatar-cached images)
        // These are no longer generated server-side as of 6.6.0 (avatar-initials npm package)
        $legacyAvatarFiles = AppIntegrityService::getLegacyAvatarFiles();
        $orphanedFiles = array_merge($orphanedFiles, $legacyAvatarFiles);

        $logger->info('Orphan file scan complete', ['count' => count($orphanedFiles)]);
        return $orphanedFiles;
    }

    /**
     * Get legacy avatar files that should be cleaned up
     * 
     * As of 6.6.0, avatar generation (initials/gravatar) moved to client-side using
     * the avatar-initials npm package. Server-side generated files are no longer needed.
     * Also includes legacy thumbnail files from pre-6.0.0 versions.
     *
     * @return array List of legacy avatar file paths relative to document root
     */
    public static function getLegacyAvatarFiles(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $documentRoot = AppIntegrityService::resolveDocumentRoot();
        $legacyFiles = [];

        foreach (['Person', 'Family'] as $type) {
            $typeDir = $documentRoot . '/Images/' . $type;
            
            if (!is_dir($typeDir)) {
                continue;
            }

            try {
                $files = @scandir($typeDir);
                if ($files === false) {
                    continue;
                }

                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') {
                        continue;
                    }

                    $fullPath = $typeDir . '/' . $file;

                    // Match legacy initials and remote (gravatar-cached) files
                    // Pattern: {id}-initials.png or {id}-remote.png
                    if (preg_match('/^\d+-(initials|remote)\.(png|jpg|jpeg|gif)$/i', $file)) {
                        $legacyFiles[] = 'Images/' . $type . '/' . $file;
                    }
                    
                    // Also scan legacy thumbnails directory (removed in 6.0.0)
                    if ($file === 'thumbnails' && is_dir($fullPath)) {
                        $thumbFiles = @scandir($fullPath);
                        if ($thumbFiles !== false) {
                            foreach ($thumbFiles as $thumbFile) {
                                if ($thumbFile === '.' || $thumbFile === '..' || $thumbFile === '.gitkeep') {
                                    continue;
                                }
                                $thumbFullPath = $fullPath . '/' . $thumbFile;
                                if (is_file($thumbFullPath)) {
                                    $legacyFiles[] = 'Images/' . $type . '/thumbnails/' . $thumbFile;
                                }
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $logger->warning('Error scanning for legacy avatar files', [
                    'directory' => $typeDir,
                    'exception' => $e,
                ]);
            }
        }

        if (count($legacyFiles) > 0) {
            $logger->info('Found legacy avatar files for cleanup', ['count' => count($legacyFiles)]);
        }

        return $legacyFiles;
    }

    /**
     * Check if a path should be excluded from orphan detection
     * Must match the same exclusions used in generate-signatures-node.js
     *
     * @param string $relativePath Path relative to src/
     * @return bool True if should be excluded
     */
    private static function isExcludedFromOrphanDetection(string $relativePath): bool
    {
        // These patterns match generate-signatures-node.js excludes array
        $excludePatterns = [
            '/^\.htaccess$/',
            '/^\.gitignore$/',
            '/^composer\.lock$/',
            '/^Include\/Config\.php$/',
            '/^propel\/propel\.php$/',
            '/^integrityCheck\.json$/',
            '/^Images\/Person\/thumbnails\//',
            '/^vendor\/.*\/example\//',
            '/^vendor\/.*\/examples\//',
            '/^vendor\/.*\/tests\//',
            '/^vendor\/.*\/Tests\//',
            '/^vendor\/.*\/test\//',
            '/^vendor\/.*\/docs\//',
        ];

        foreach ($excludePatterns as $pattern) {
            if (preg_match($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively scan a directory for orphaned files
     *
     * @param string $currentPath The path to scan
     * @param string $basePath The base path for relative path calculation (src/)
     * @param array $validFiles Array of valid file paths from signatures (relative to src/)
     * @return array List of orphaned file paths (relative to src/)
     */
    private static function scanDirectoryForOrphans(string $currentPath, string $basePath, array $validFiles): array
    {
        $orphanedFiles = [];

        // Directories to skip entirely (not scanned at all)
        // Note: Include/Config.php is handled by isExcludedFromOrphanDetection()
        $skipDirs = ['logs', 'temp' ];

        try {
            $items = @scandir($currentPath);
            if ($items === false) {
                return [];
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $fullPath = $currentPath . '/' . $item;

                // Calculate path relative to basePath (src/)
                $relativePath = ltrim(str_replace($basePath, '', $fullPath), '/');
                $relativePath = str_replace('\\', '/', $relativePath);

                if (is_dir($fullPath)) {
                    // Skip certain directories
                    if (in_array($item, $skipDirs)) {
                        continue;
                    }
                    // Skip vendor subdirectories that are excluded from signatures
                    if (self::isExcludedFromOrphanDetection($relativePath . '/')) {
                        continue;
                    }
                    // Recursively scan subdirectories
                    $orphanedFiles = array_merge(
                        $orphanedFiles,
                        AppIntegrityService::scanDirectoryForOrphans($fullPath, $basePath, $validFiles)
                    );
                } elseif (is_file($fullPath) && preg_match('/\.(php|js)$/i', $item)) {
                    // Skip files that are excluded from signatures
                    if (self::isExcludedFromOrphanDetection($relativePath)) {
                        continue;
                    }
                    // Check if file is in valid list
                    if (!isset($validFiles[$relativePath])) {
                        $orphanedFiles[] = $relativePath;
                    }
                }
            }
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Error scanning directory for orphans', [
                'path' => $currentPath,
                'exception' => $e,
            ]);
        }

        return $orphanedFiles;
    }

    /**
     * Delete all orphaned files found on the system
     *
     * @return array Result array with 'deleted', 'failed', and 'errors' keys
     */
    public static function deleteOrphanedFiles(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $documentRoot = AppIntegrityService::resolveDocumentRoot();
        $orphanedFiles = AppIntegrityService::getOrphanedFiles();
        $result = [
            'deleted' => [],
            'failed' => [],
            'errors' => [],
        ];

        foreach ($orphanedFiles as $filePath) {
            $fullPath = $documentRoot . '/' . $filePath;
            try {
                if (is_file($fullPath)) {
                    if (@unlink($fullPath)) {
                        $result['deleted'][] = $filePath;
                        $logger->info('Deleted orphaned file', ['file' => $filePath]);
                    } else {
                        $result['failed'][] = $filePath;
                        $result['errors'][] = sprintf('Failed to delete: %s', $filePath);
                        $logger->warning('Failed to delete orphaned file', ['file' => $filePath]);
                    }
                }
            } catch (\Exception $e) {
                $result['failed'][] = $filePath;
                $result['errors'][] = $e->getMessage();
                $logger->error('Error deleting orphaned file', [
                    'file' => $filePath,
                    'exception' => $e,
                ]);
            }
        }

        return $result;
    }

    /**
     * Get available system locales
     * Detects which locales are installed on the system
     *
     * @return array Locale codes available on the system
     */
    public static function getAvailableSystemLocales(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $availableLocales = [];

        // First, try to get locales using locale -a command (Unix/Linux/macOS)
        if (function_exists('exec')) {
            $output = [];
            try {
                @exec('locale -a 2>/dev/null', $output, $returnCode);
                if ($returnCode === 0 && count($output) > 0) {
                    // Parse output and normalize locale names
                    foreach ($output as $line) {
                        $line = trim($line);
                        if (!empty($line)) {
                            // Normalize the locale name (remove .utf8, .UTF-8, @modifiers, etc.)
                            $locale = preg_replace('/(\.[^.]*)?(@.*)?$/', '', $line);
                            if (!empty($locale)) {
                                $availableLocales[] = $locale;
                            }
                        }
                    }
                    $availableLocales = array_unique($availableLocales);
                    $logger->debug('System locales detected via locale command', [
                        'count' => count($availableLocales),
                    ]);

                    return $availableLocales;
                }
            } catch (\Exception $e) {
                $logger->debug('Error executing locale command', ['exception' => $e]);
            }
        }

        // Fallback: Check commonly available locales using setlocale
        $commonLocales = [
            'C',
            'en_US',
            'en_US.UTF-8',
            'en_GB',
            'en_GB.UTF-8',
            'de_DE',
            'de_DE.UTF-8',
            'fr_FR',
            'fr_FR.UTF-8',
            'es_ES',
            'es_ES.UTF-8',
            'it_IT',
            'it_IT.UTF-8',
            'pt_BR',
            'pt_BR.UTF-8',
            'pt_PT',
            'pt_PT.UTF-8',
            'nl_NL',
            'nl_NL.UTF-8',
            'ja_JP',
            'ja_JP.UTF-8',
            'zh_CN',
            'zh_CN.UTF-8',
            'zh_TW',
            'zh_TW.UTF-8',
            'ko_KR',
            'ko_KR.UTF-8',
            'ru_RU',
            'ru_RU.UTF-8',
            'pl_PL',
            'pl_PL.UTF-8',
            'ar_EG',
            'ar_EG.UTF-8',
        ];

        // Save current locale before testing
        $originalLocale = setlocale(LC_ALL, 0);

        foreach ($commonLocales as $locale) {
            $currentLocale = setlocale(LC_ALL, $locale);
            if ($currentLocale !== false && $currentLocale !== 'C') {
                // Normalize the locale name
                $normalized = preg_replace('/(\.[^.]*)?(@.*)?$/', '', $locale);
                if (!empty($normalized)) {
                    $availableLocales[] = $normalized;
                }
            }
        }

        // Restore original locale
        if ($originalLocale !== false) {
            setlocale(LC_ALL, $originalLocale);
        }

        $availableLocales = array_unique($availableLocales);
        $logger->debug('System locales detected via setlocale fallback', [
            'count' => count($availableLocales),
        ]);

        return $availableLocales;
    }

    /**
     * Get ChurchCRM supported locales
     *
     * @return array Array with locale code as key and full locale info as value
     */
    public static function getSupportedLocales(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $documentRoot = AppIntegrityService::resolveDocumentRoot();
        $localesFile = $documentRoot . '/locale/locales.json';

        try {
            if (!is_file($localesFile)) {
                $logger->warning('Locales file not found', ['file' => $localesFile]);

                return [];
            }

            $localesContent = file_get_contents($localesFile);
            if ($localesContent === false) {
                $logger->warning('Failed to read locales file', ['file' => $localesFile]);

                return [];
            }

            $locales = json_decode($localesContent, true, 512, JSON_THROW_ON_ERROR);

            return $locales ?? [];
        } catch (\Exception $e) {
            $logger->warning('Error loading supported locales', ['exception' => $e]);

            return [];
        }
    }

    /**
     * Get locale support information for setup wizard
     * Returns supported locales with indicator of system availability
     *
     * @return array Locale support data with system availability flags
     */
    public static function getLocaleSetupInfo(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $supportedLocales = AppIntegrityService::getSupportedLocales();
        $availableLocales = AppIntegrityService::getAvailableSystemLocales();

        // Normalize available locales for comparison (remove encoding suffixes)
        $availableNormalized = array_map(function ($locale) {
            return preg_replace('/(\.[^.]*)?(@.*)?$/', '', $locale);
        }, $availableLocales);
        $availableNormalized = array_unique($availableNormalized);

        $localeInfo = [];
        $systemSupported = 0;
        $totalSupported = 0;

        foreach ($supportedLocales as $languageName => $localeConfig) {
            $localeCode = $localeConfig['locale'] ?? '';
            $languageCode = $localeConfig['languageCode'] ?? '';
            $totalSupported++;

            // Check if this locale is available on the system
            $isAvailable = in_array($localeCode, $availableNormalized, true) ||
                          in_array($languageCode, $availableNormalized, true) ||
                          in_array(str_replace('_', '-', $localeCode), $availableNormalized, true);

            if ($isAvailable) {
                $systemSupported++;
            }

            $localeInfo[] = [
                'name' => $languageName,
                'locale' => $localeCode,
                'languageCode' => $languageCode,
                'systemAvailable' => $isAvailable,
                'countryCode' => $localeConfig['countryCode'] ?? '',
            ];
        }

        $logger->info('Locale setup info generated', [
            'totalSupported' => $totalSupported,
            'systemSupported' => $systemSupported,
        ]);

        return [
            'supportedLocales' => $localeInfo,
            'availableSystemLocales' => $availableLocales,
            'systemLocaleSupportSummary' => $systemSupported . '/' . $totalSupported . ' locales available on system',
            'systemLocaleDetected' => count($availableLocales) > 0,
        ];
    }
}
