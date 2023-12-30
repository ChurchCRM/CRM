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
        if (AppIntegrityService::$IntegrityCheckDetails === null) {
            $logger->debug('Integrity check results not cached; reloading from file');
            if (is_file($integrityCheckFile)) {
                $logger->info('Integrity check result file found at: ' . $integrityCheckFile);

                try {
                    $integrityCheckFileContents = file_get_contents($integrityCheckFile);
                    MiscUtils::throwIfFailed($integrityCheckFileContents);
                    AppIntegrityService::$IntegrityCheckDetails = json_decode($integrityCheckFileContents, null, 512, JSON_THROW_ON_ERROR);
                } catch (\Exception $e) {
                    $logger->warning('Error decoding integrity check result file: ' . $integrityCheckFile, ['exception' => $e]);
                    AppIntegrityService::$IntegrityCheckDetails = new \stdClass();
                    AppIntegrityService::$IntegrityCheckDetails->status = 'failure';
                    AppIntegrityService::$IntegrityCheckDetails->message = gettext('Error decoding integrity check result file');
                }
            } else {
                $logger->debug('Integrity check result file not found at: ' . $integrityCheckFile);
                AppIntegrityService::$IntegrityCheckDetails = new \stdClass();
                AppIntegrityService::$IntegrityCheckDetails->status = 'failure';
                AppIntegrityService::$IntegrityCheckDetails->message = gettext('integrityCheck.json file missing');
            }
        } else {
            $logger->debug('Integrity check results already cached; not reloading from file');
        }

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
        if (AppIntegrityService::getIntegrityCheckData()->status !== 'failure') {
            AppIntegrityService::$IntegrityCheckDetails->message = gettext('The previous integrity check passed.  All system file hashes match the expected values.');
        }

        return AppIntegrityService::$IntegrityCheckDetails->message;
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
            $logger->info('Signature file found at: ' . $signatureFile);

            try {
                $signatureFileContents = file_get_contents($signatureFile);
                MiscUtils::throwIfFailed($signatureFileContents);
                $signatureData = json_decode($signatureFileContents, null, 512, JSON_THROW_ON_ERROR);
            } catch (\Exception $e) {
                $logger->warning('Error decoding signature definition file: ' . $signatureFile, ['exception' => $e]);

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
                        if ($actualHash != $file->sha1) {
                            $logger->warning('File hash mismatch: ' . $file->filename . '. Expected: ' . $file->sha1 . '; Got: ' . $actualHash);
                            $signatureFailures[] = [
                                'filename' => $file->filename,
                                'status' => 'Hash Mismatch',
                                'expectedhash' => $file->sha1,
                                'actualhash' => $actualHash,
                            ];
                        }
                    } else {
                        $logger->warning('File Missing: ' . $file->filename);
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
            $logger->warning('Signature definition file not found at: ' . $signatureFile);

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
        } else {
            return [
                'status' => 'success',
            ];
        }
    }

    private static function testImagesWriteable(): bool
    {
        return is_writable(SystemURLs::getDocumentRoot() . '/Images/') &&
            is_writable(SystemURLs::getDocumentRoot() . '/Images/Family') &&
            is_writable(SystemURLs::getDocumentRoot() . '/Images/Person');
    }

    /**
     * @return Prerequisite[]
     */
    public static function getApplicationPrerequisites(): array
    {
        $prerequisites = [
            new Prerequisite('PHP 8.1+', fn () => version_compare(PHP_VERSION, '8.1.0', '>=')),
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
            new Prerequisite('Include/Config file is writeable', fn (): bool => is_writable(SystemURLs::getDocumentRoot() . '/Include/') || is_writable(SystemURLs::getDocumentRoot() . '/Include/Config.php')),
            new Prerequisite('Images directory is writeable', fn (): bool => AppIntegrityService::testImagesWriteable()),
            new Prerequisite('PHP ZipArchive', fn (): bool => extension_loaded('zip')),
            new Prerequisite('Mysqli Functions', fn (): bool => function_exists('mysqli_connect')),
        ];

        return $prerequisites;
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
        // mod_rewrite can be tricky to detect properly.
        // First check if it's loaded as an apache module
        // Second check (if supported) if apache cli lists the module
        // Third, finally try calling a known invalid URL on this installation
        //   and check for a header that would only be present if .htaccess was processed.
        //   This header comes from index.php (which is the target of .htaccess for invalid URLs)

        $check = false;
        $logger = LoggerUtils::getAppLogger();

        if (isset($_SERVER['HTTP_MOD_REWRITE'])) {
            $logger->debug("Webserver configuration has set mod_rewrite variable: {$_SERVER['HTTP_MOD_REWRITE']}");
            $check = strtolower($_SERVER['HTTP_MOD_REWRITE']) === 'on';
        } elseif (stristr($_SERVER['SERVER_SOFTWARE'], 'apache') !== false) {
            $logger->debug('PHP is running through Apache; looking for mod_rewrite');
            if (function_exists('apache_get_modules')) {
                $check = in_array('mod_rewrite', apache_get_modules());
            }
            $logger->debug("Apache mod_rewrite check status: $check");
        } else {
            $logger->debug('PHP is not running through Apache');
        }

        if ($check === false) {
            $logger->debug('Previous rewrite checks failed');
            if (function_exists('curl_version')) {
                $ch = curl_init();
                $request_url_parser = parse_url($_SERVER['HTTP_REFERER']);
                $request_scheme = $request_url_parser['scheme'] ?? 'http';
                $rewrite_chk_url = $request_scheme . '://' . $_SERVER['SERVER_ADDR'] . SystemURLs::getRootPath() . '/INVALID';
                $logger->debug("Testing CURL loopback check to: $rewrite_chk_url");
                curl_setopt($ch, CURLOPT_URL, $rewrite_chk_url);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HEADER, 1);
                curl_setopt($ch, CURLOPT_NOBODY, 1);
                $output = curl_exec($ch);
                curl_close($ch);
                $headers = [];
                $data = explode("\n", $output);
                $headers['status'] = $data[0];
                array_shift($data);
                foreach ($data as $part) {
                    if (strpos($part, ':')) {
                        $middle = explode(':', $part);
                        $headers[trim($middle[0])] = trim($middle[1]);
                    }
                }
                $check = $headers['CRM'] === 'would redirect';
                $logger->debug('CURL loopback check headers observed: ' . ($check ? 'true' : 'false'));
            }
        }

        return $check;
    }
}
