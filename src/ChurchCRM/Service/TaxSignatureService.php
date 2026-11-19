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

    /** Default folder, inside the app, relative to the document root. */
    public const DEFAULT_DIRECTORY = 'Images/TaxSignatures';

    /** Written into the storage folder on demand so it is never served directly. */
    private const GUARD_HTACCESS = <<<'HTACCESS'
# Deny direct HTTP access to the private giving-statement signature.
# It is served only through the authenticated admin route:
#   GET /admin/api/system/tax-signature/image
Require all denied

HTACCESS;

    private static function directory(): string
    {
        $root = realpath(SystemURLs::getDocumentRoot());
        if ($root === false) {
            throw new ImageStorageException(gettext('Cannot resolve the application folder for tax signature storage.'));
        }

        $configured = trim((string) SystemConfig::getValue('sTaxSignatureStoragePath'));
        if ($configured === '') {
            $directory = $root . '/' . self::DEFAULT_DIRECTORY;
        } else {
            $isAbsolute = str_starts_with($configured, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $configured) === 1;
            if (!$isAbsolute || str_contains($configured, "\0") || preg_match('#(^|[\\\\/])\.\.([\\\\/]|$)#', $configured) === 1) {
                throw new ImageStorageException(gettext('The tax signature storage path setting must be an absolute folder path.'));
            }
            $directory = rtrim($configured, '/\\');
            if ($directory === '') {
                throw new ImageStorageException(gettext('The tax signature storage path setting must be an absolute folder path.'));
            }
            $resolved = realpath($directory);
            $checked = $resolved === false ? $directory : $resolved;
            if (str_starts_with($checked . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)
                && $checked !== $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::DEFAULT_DIRECTORY)) {
                throw new ImageStorageException(gettext('The tax signature storage path must be outside the ChurchCRM folder. Leave the setting blank to use the built-in private folder.'));
            }
        }

        if (is_link($directory)) {
            throw new ImageStorageException(gettext('The tax signature storage folder cannot be a symbolic link.'));
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new ImageStorageException(sprintf(gettext('Cannot create the tax signature storage folder %s. Make sure the web server can write to it, or set a writable folder in the tax signature storage path setting.'), $directory));
        }
        if ($configured !== '') {
            $created = realpath($directory);
            if ($created === false || str_starts_with($created . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
                throw new ImageStorageException(gettext('The tax signature storage path must be outside the ChurchCRM folder. Leave the setting blank to use the built-in private folder.'));
            }
            $directory = $created;
        }
        clearstatcache(true, $directory);
        if (!is_writable($directory)) {
            throw new ImageStorageException(sprintf(gettext('The tax signature storage folder %s is not writable by the web server.'), $directory));
        }
        self::writeGuards($directory);

        return $directory;
    }

    /** Deny-all guards: Apache honours .htaccess; index.html stops directory listings. */
    private static function writeGuards(string $directory): void
    {
        $guards = ['.htaccess' => self::GUARD_HTACCESS, 'index.html' => ''];
        foreach ($guards as $name => $contents) {
            $path = $directory . '/' . $name;
            if (is_link($path)) {
                throw new ImageStorageException(gettext('Invalid tax signature storage.'));
            }
            if (!is_file($path) && @file_put_contents($path, $contents) === false) {
                throw new ImageStorageException(sprintf(gettext('Cannot write the access guard %s in the tax signature storage folder.'), $name));
            }
        }
    }

    private static function storedPath(): ?string
    {
        $identifier = (string) SystemConfig::getValue('sTaxSignatureAsset');
        if ($identifier === '') {
            return null;
        }
        if (!preg_match('/^[a-f0-9]{64}\.png$/D', $identifier)) {
            throw new ImageStorageException(gettext('Invalid stored tax signature identifier.'));
        }
        $path = self::directory() . '/' . $identifier;
        if (is_link($path)) {
            throw new ImageStorageException(gettext('Invalid tax signature storage.'));
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
            throw new ImageStorageException(gettext('Cannot read the private tax signature.'));
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
                throw new ImageStorageException(gettext('Cannot create the private tax signature file. Check that the storage folder is writable.'));
            }
            imagealphablending($image, false);
            imagesavealpha($image, true);
            if (!imagepng($image, $stream) || !fflush($stream) || fstat($stream)['size'] === 0) {
                throw new ImageStorageException(gettext('Cannot encode the private tax signature.'));
            }
            fclose($stream);
            $stream = null;
            SystemConfig::setValue('sTaxSignatureAsset', $identifier, true);
            $saved = true;
            if ($oldPath !== null && is_file($oldPath) && !@unlink($oldPath)) {
                throw new ImageStorageException(gettext('Signature updated but the previous private signature file could not be removed.'));
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
            throw new ImageStorageException(gettext('Cannot remove the private tax signature file.'));
        }
        SystemConfig::setValue('sTaxSignatureAsset', '', true);
    }
}
