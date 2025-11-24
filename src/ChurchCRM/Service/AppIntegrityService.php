<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Prerequisite;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

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
        return [
            new Prerequisite('PHP 8.2+', fn (): bool => PHP_VERSION_ID >= 80200),
            new Prerequisite('PCRE and UTF-8 Support', fn (): bool => function_exists('preg_match') && @preg_match('/^.$/u', 'ñ') && @preg_match('/^\pL$/u', 'ñ')),
            new Prerequisite('Multibyte Encoding', fn (): bool => function_exists('mb_strlen')),
            new Prerequisite('PHP Phar', fn (): bool => class_exists('PharData')),
            new Prerequisite('PHP Session', fn (): bool => function_exists('session_start')),
            new Prerequisite('PHP XML', fn (): bool => class_exists('SimpleXMLElement')),
            new Prerequisite('PHP EXIF', fn (): bool => function_exists('exif_imagetype')),
            new Prerequisite('PHP iconv', fn (): bool => function_exists('iconv')),
            new Prerequisite('Mod Rewrite or Equivalent', fn (): bool => AppIntegrityService::hasModRewrite()),
            new Prerequisite(
                'GD Library for image manipulation',
                fn (): bool =>
                    function_exists('imagecreatetruecolor') &&
                    function_exists('gd_info') &&
                    function_exists('imagecolorallocate') &&
                    function_exists('imagefilledrectangle') &&
                    function_exists('imageftbbox') &&
                    function_exists('imagefttext') &&
                    function_exists('imagepng')
            ),
            new Prerequisite('FreeType Library', fn (): bool => function_exists('imagefttext')),
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

            $request_url_parser = parse_url($_SERVER['HTTP_REFERER']);
            $request_scheme = $request_url_parser['scheme'] ?? 'http';
            $request_host = $request_url_parser['host'] ?? $_SERVER['HTTP_HOST'];
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
}
