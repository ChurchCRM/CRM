<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\ImageSupportUtils;
use ChurchCRM\Utils\LoggerUtils;

/**
 * ChurchLogoService — stores and removes the single church logo that ChurchCRM
 * uses for the sidebar brand, the login/auth pages and email templates.
 *
 * The logo is a single site-wide file (`Images/church-logo.png`), so it has no
 * owning record and cannot reuse `Photo`, which is keyed by photo type + id.
 * The upload pipeline is shared with person/family photos through
 * `ImageSupportUtils` (MIME allow-list, no SVG, server upload-size cap,
 * downscale-to-fit with alpha preserved) and the result is always re-encoded
 * to PNG.
 *
 * The resolver that decides between the uploaded logo, the `sChurchLogoURL`
 * fallback and the bundled ChurchCRM logo lives in
 * `ChurchCRM\dto\ChurchMetaData::getChurchLogoURL()` / `::getChurchLogoPath()`.
 */
class ChurchLogoService
{
    /** File name of the uploaded logo, relative to the Images directory. */
    public const LOGO_FILENAME = 'church-logo.png';

    /**
     * Maximum stored logo dimensions. Uploads are scaled down to fit inside this
     * box with the aspect ratio preserved; smaller images are never upscaled.
     * A wide banner (roughly 3.5:1) is what the login card and sidebar expect.
     */
    public const LOGO_MAX_WIDTH = 1200;
    public const LOGO_MAX_HEIGHT = 400;

    /** Absolute filesystem path of the uploaded logo (whether or not it exists). */
    public static function getLogoPath(): string
    {
        return SystemURLs::getImagesRoot() . '/' . self::LOGO_FILENAME;
    }

    /** True when an administrator has uploaded a logo. */
    public static function hasCustomLogo(): bool
    {
        return is_file(self::getLogoPath());
    }

    /**
     * Last-modified timestamp of the uploaded logo, or 0 when none exists.
     * Used as a cache-busting `?v=` token so re-uploading produces a fresh
     * browser fetch of the same URL (same approach as Photo, see #8662).
     */
    public static function getModifiedTime(): int
    {
        if (!self::hasCustomLogo()) {
            return 0;
        }

        $mtime = @filemtime(self::getLogoPath());

        return $mtime === false ? 0 : $mtime;
    }

    /**
     * Store an uploaded logo from a base64 data URI, re-encoded to PNG.
     *
     * @throws \Exception when the payload is not a supported image
     * @throws \ChurchCRM\Exceptions\PhotoSizeException when it exceeds the server upload limit
     */
    public static function setImageFromBase64(string $base64): void
    {
        self::ensureImagesDirExists();

        // Shared decode + MIME allow-list + upload-size validation
        $fileData = ImageSupportUtils::decodeBase64Image($base64);

        $resizedImage = ImageSupportUtils::createResizedImage(
            $fileData,
            self::LOGO_MAX_WIDTH,
            self::LOGO_MAX_HEIGHT
        );

        if (!imagepng($resizedImage, self::getLogoPath())) {
            throw new \Exception('Failed to save the church logo');
        }

        LoggerUtils::getAppLogger()->info('Church logo uploaded', [
            'path'   => self::getLogoPath(),
            'width'  => imagesx($resizedImage),
            'height' => imagesy($resizedImage),
        ]);
    }

    /**
     * Remove the uploaded logo. Idempotent — returns true when no logo exists.
     */
    public static function delete(): bool
    {
        if (!self::hasCustomLogo()) {
            return true;
        }

        $deleted = @unlink(self::getLogoPath());

        if ($deleted) {
            LoggerUtils::getAppLogger()->info('Church logo removed', ['path' => self::getLogoPath()]);
        } else {
            LoggerUtils::getAppLogger()->error('Failed to remove church logo', ['path' => self::getLogoPath()]);
        }

        return $deleted;
    }

    private static function ensureImagesDirExists(): void
    {
        $imagesRoot = SystemURLs::getImagesRoot();
        if (!is_dir($imagesRoot)) {
            @mkdir($imagesRoot, 0755, true);
        }
    }
}
