<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Exceptions\ImageStorageException;
use ChurchCRM\Exceptions\PhotoSizeException;
use InvalidArgumentException;

class TaxSignatureService
{
    private const MAX_BYTES = 2097152;
    private const MAX_WIDTH = 2000;
    private const MAX_HEIGHT = 1000;

    private static function directory(): string
    {
        $root = realpath(SystemURLs::getDocumentRoot());
        if ($root === false) {
            throw new ImageStorageException('Cannot resolve application storage');
        }

        $webRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? $root);
        if ($webRoot === false || !is_dir($webRoot)) {
            throw new ImageStorageException('Cannot resolve the web document root');
        }

        // A subdirectory installation must also stay outside its containing web root.
        $directory = dirname($webRoot) . '/.churchcrm-tax-signatures-' . substr(hash('sha256', $root), 0, 24);
        if (str_starts_with($directory . DIRECTORY_SEPARATOR, rtrim($webRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new ImageStorageException('Private signature storage cannot be inside the web document root');
        }
        if (is_link($directory)
            || (!is_dir($directory) && !@mkdir($directory, 0700) && !is_dir($directory))) {
            throw new ImageStorageException('Cannot create private tax signature storage outside the application document root');
        }
        clearstatcache(true, $directory);
        if (!is_writable($directory)
            || (DIRECTORY_SEPARATOR === '/' && (fileperms($directory) & 0077) !== 0)
            || (function_exists('posix_geteuid') && fileowner($directory) !== posix_geteuid())) {
            throw new ImageStorageException('Tax signature storage must be private and writable');
        }

        return $directory;
    }

    private static function storedPath(): ?string
    {
        $identifier = (string) SystemConfig::getValue('sCanadianTaxSignatureAsset');
        if ($identifier === '') {
            return null;
        }
        if (!preg_match('/^[a-f0-9]{64}\.png$/D', $identifier)) {
            throw new ImageStorageException('Invalid stored tax signature identifier');
        }
        $path = self::directory() . '/' . $identifier;
        if (is_link($path)) {
            throw new ImageStorageException('Invalid tax signature storage');
        }

        return $path;
    }

    public static function hasUploaded(): bool
    {
        $path = self::storedPath();

        return $path !== null && is_file($path) && is_readable($path);
    }

    public static function image(): ?string
    {
        $path = self::storedPath();
        if ($path === null || !is_file($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new ImageStorageException('Cannot read the private tax signature');
        }

        return $contents;
    }

    public static function setImageFromBase64(string $payload): void
    {
        if (strlen($payload) > 4 * (int) ceil(self::MAX_BYTES / 3) + 32) {
            throw new PhotoSizeException(gettext('The signature image must be no larger than 2 MiB.'));
        }
        if (!preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/]*={0,2})$#D', $payload, $matches)) {
            throw new InvalidArgumentException(gettext('Upload a PNG or JPEG signature image.'));
        }
        $bytes = base64_decode($matches[2], true);
        if ($bytes === false || $bytes === '') {
            throw new InvalidArgumentException(gettext('Invalid signature image data.'));
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new PhotoSizeException(gettext('The signature image must be no larger than 2 MiB.'));
        }
        $dimensions = @getimagesizefromstring($bytes);
        $expectedType = $matches[1] === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
        if ($dimensions === false || $dimensions[2] !== $expectedType) {
            throw new InvalidArgumentException(gettext('Upload a PNG or JPEG signature image.'));
        }
        if ($dimensions[0] < 1 || $dimensions[1] < 1 || $dimensions[0] > self::MAX_WIDTH || $dimensions[1] > self::MAX_HEIGHT) {
            throw new PhotoSizeException(gettext('The signature image must fit within 2000 × 1000 pixels.'));
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new InvalidArgumentException(gettext('The signature image could not be decoded.'));
        }

        $path = null;
        $stream = null;
        $saved = false;
        try {
            $oldPath = self::storedPath();
            $identifier = bin2hex(random_bytes(32)) . '.png';
            $path = self::directory() . '/' . $identifier;
            $stream = @fopen($path, 'xb');
            if ($stream === false || !@chmod($path, 0600)) {
                throw new ImageStorageException('Cannot create the private tax signature');
            }
            imagealphablending($image, false);
            imagesavealpha($image, true);
            if (!imagepng($image, $stream) || !fflush($stream) || fstat($stream)['size'] === 0) {
                throw new ImageStorageException('Cannot encode the private tax signature');
            }
            fclose($stream);
            $stream = null;
            SystemConfig::setValue('sCanadianTaxSignatureAsset', $identifier, true);
            $saved = true;
            if ($oldPath !== null && is_file($oldPath) && !@unlink($oldPath)) {
                throw new ImageStorageException('Signature updated but the previous private asset could not be removed');
            }
        } finally {
            imagedestroy($image);
            if (is_resource($stream)) {
                fclose($stream);
            }
            if (!$saved && $path !== null && is_file($path)) {
                @unlink($path);
            }
        }
    }

    public static function delete(): void
    {
        $path = self::storedPath();
        if ($path !== null && is_file($path) && !@unlink($path)) {
            throw new ImageStorageException('Cannot remove the private tax signature');
        }
        SystemConfig::setValue('sCanadianTaxSignatureAsset', '', true);
    }
}
