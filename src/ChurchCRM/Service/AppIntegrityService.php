<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\Prerequisite;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

class AppIntegrityService
{
    private static $IntegrityCheckDetails;

    private static function getIntegrityCheckData()
    {
        $logger = LoggerUtils::getAppLogger();
        $integrityCheckFile = SystemURLs::getDocumentRoot() . '/integrityCheck.json';
        if (AppIntegrityService::$IntegrityCheckDetails !== null) {
            $logger->debug('Integrity check results already cached; not reloading from file');

            return AppIntegrityService::$IntegrityCheckDetails;
        }

        $logger->debug('Integrity check results not cached; reloading from file');

        if (is_file($integrityCheckFile)) {
            $logger->debug("Integrity check result file found at: {integrityCheckFile}", [
                'integrityCheckFile' => $integrityCheckFile,
            ]);

            try {
                $integrityCheckFileContents = file_get_contents($integrityCheckFile);
                MiscUtils::throwIfFailed($integrityCheckFileContents);
                AppIntegrityService::$IntegrityCheckDetails = json_decode($integrityCheckFileContents, null, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $e) {
                $logger->warning("Error decoding integrity check result file: {integrityCheckFile}", [
                    'integrityCheckFile' => $integrityCheckFile,
                    'exception' => $e,
                ]);

                AppIntegrityService::$IntegrityCheckDetails = new \stdClass();
                AppIntegrityService::$IntegrityCheckDetails->status = 'failure';
                AppIntegrityService::$IntegrityCheckDetails->message = gettext('Error decoding integrity check result file');
            }

            return AppIntegrityService::$IntegrityCheckDetails;
        }

        $logger->debug("Integrity check result file not found at: {integrityCheckFile}", [
                    'integrityCheckFile' => $integrityCheckFile,
                ]);

        AppIntegrityService::$IntegrityCheckDetails = new \stdClass();
        AppIntegrityService::$IntegrityCheckDetails->status = 'failure';
        AppIntegrityService::$IntegrityCheckDetails->message = gettext('integrityCheck.json file missing');

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
        if (AppIntegrityService::getIntegrityCheckStatus() === 'Passed') {
            AppIntegrityService::$IntegrityCheckDetails->message = gettext('The previous integrity check passed. All system file hashes match the expected values.');
        }

        return AppIntegrityService::$IntegrityCheckDetails->message ?? '';
    }

    public static function getFilesFailingIntegrityCheck()
    {
        return AppIntegrityService::getIntegrityCheckData()->files ?? [];
    }

    public static function verifyApplicationIntegrity(): array
    {
        $logger = LoggerUtils::getAppLogger();
        $signatureFile = SystemURLs::getDocumentRoot() . '/signatures.json';
        $signatureFailures = [];
        if (is_file($signatureFile)) {
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
                    $currentFile = SystemURLs::getDocumentRoot() . '/' . $file->filename;
                    if (is_file($currentFile)) {
                        $actualHash = sha1_file($currentFile);
                        if ($actualHash !== $file->sha1) {
                            $logger->warning("File hash mismatch: {filename}. Expected: {expectedHash}; Got: {actualHash}", [
                                'filename' => $file->filename,
                                'expectedHash' => $file->sha1,
                                'actualHash' => $actualHash,
                            ]);
                            $signatureFailures[] = [
                                'filename' => $file->filename,
                                'status' => 'Hash Mismatch',
                                'expectedhash' => $file->sha1,
                                'actualhash' => $actualHash,
                            ];
                        }
                    } else {
                        $logger->warning("File Missing: {filename}", [
                            'filename' => $file->filename,
                        ]);
                        $signatureFailures[] = [
                            'filename' => $file->filename,
                            'status' => 'File Missing',
                        ];
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
                'message' => gettext('Signature definition File Missing'),
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
            'status' => 'success',
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
            new Prerequisite('PHP 8.1+', fn (): bool => version_compare(PHP_VERSION, '8.1.0', '>=')),
            new Prerequisite('PCRE and UTF-8 Support', fn (): bool => function_exists('preg_match') && @preg_match('/^.$/u', 'ñ') && @preg_match('/^\pL$/u', 'ñ')),
            new Prerequisite('Multibyte Encoding', fn (): bool => extension_loaded('mbstring')),
            new Prerequisite('PHP Phar', fn (): bool => extension_loaded('phar')),
            new Prerequisite('PHP Session', fn (): bool => extension_loaded('session')),
            new Prerequisite('PHP XML', fn (): bool => extension_loaded('xml')),
            new Prerequisite('PHP EXIF', fn (): bool => extension_loaded('exif')),
            new Prerequisite('PHP iconv', fn (): bool => extension_loaded('iconv')),
            new Prerequisite('Mod Rewrite or Equivalent', fn (): bool => AppIntegrityService::hasModRewrite()),
            new Prerequisite('GD Library for image manipulation', fn (): bool => extension_loaded('gd') && function_exists('gd_info')),
            new Prerequisite('FreeType Library', fn (): bool => function_exists('imagettftext')),
            new Prerequisite('FileInfo Extension for image manipulation', fn (): bool => extension_loaded('fileinfo')),
            new Prerequisite('cURL', fn (): bool => function_exists('curl_version')),
            new Prerequisite('locale gettext', fn (): bool => function_exists('bindtextdomain') && function_exists('gettext')),
            new Prerequisite('Include/Config file is writeable', fn (): bool => AppIntegrityService::verifyDirectoryWriteable(SystemURLs::getDocumentRoot() . '/Include/') && is_writable(SystemURLs::getDocumentRoot() . '/Include/Config.php')),
            new Prerequisite('Images directory is writeable', fn (): bool => AppIntegrityService::verifyDirectoryWriteable(SystemURLs::getDocumentRoot() . '/Images/')),
            new Prerequisite('Family images directory is writeable', fn (): bool => AppIntegrityService::verifyDirectoryWriteable(SystemURLs::getDocumentRoot() . '/Images/Family')),
            new Prerequisite('Person images directory is writeable', fn (): bool => AppIntegrityService::verifyDirectoryWriteable(SystemURLs::getDocumentRoot() . '/Images/Person')),
            new Prerequisite('PHP ZipArchive', fn (): bool => extension_loaded('zip')),
            new Prerequisite('Mysqli Functions', fn (): bool => function_exists('mysqli_connect')),
        ];
    }

    /**
     * @return Prerequisite[]
     */
    public static function getUnmetPrerequisites(): array
    {
        return array_filter(
            AppIntegrityService::getApplicationPrerequisites(),
            fn ($prereq): bool => !$prereq->isPrerequisiteMet()
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

            $header = [];
            $headers = substr($result, 0, strpos($result, "\r\n\r\n"));

            foreach (explode("\r\n", $headers) as $lineKey => $line) {
                if ($lineKey === 0) {
                    $header['status'] = $line;
                }

                [$key, $value] = explode(': ', $line);
                $header[$key] = $value;
            }

            $logger->debug('CURL loopback check header observed: ' . (array_key_exists('crm', $header) ? 'true' : 'false'));
            return array_key_exists('crm', $header) && $header['crm'] === 'would redirect';
        }

        return false;
    }
}
