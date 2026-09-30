<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Exceptions\PhotoSizeException;
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
     * URL of the uploaded logo relative to the application root, or null when
     * none is stored.
     *
     * The `?v=` token is a hash of the stored bytes, so the URL changes whenever
     * the content does, even for two replacements inside the same second, which
     * a filemtime() token (Photo's approach, #8662) cannot promise. xxh3 hashes
     * the at-most-1200x400 PNG in well under a millisecond.
     */
    public static function getUrlPath(): ?string
    {
        $path = self::getLogoPath();
        $hash = is_file($path) ? hash_file('xxh3', $path) : false;

        return $hash === false ? null : '/Images/' . self::LOGO_FILENAME . '?v=' . $hash;
    }

    /**
     * Store an uploaded logo from a base64 data URI, re-encoded to PNG.
     *
     * The logo is site-wide and served while this runs, so the new PNG is
     * encoded to a temporary file and renamed over the live one: a failed
     * upload (bad image, over the size limit, disk full) leaves the current logo
     * exactly as it was, and readers never see a half-written file.
     *
     * @throws \Exception when the payload is not a supported image
     * @throws PhotoSizeException when it exceeds the server upload limit or the source image size limit
     */
    public static function setImageFromBase64(string $base64): void
    {
        self::ensureImagesDirExists();

        // Shared decode + MIME allow-list + upload-size validation
        $fileData = ImageSupportUtils::decodeBase64Image($base64);

        // Also checks the source dimensions against the size limit first
        $resizedImage = ImageSupportUtils::createResizedImage(
            $fileData,
            self::LOGO_MAX_WIDTH,
            self::LOGO_MAX_HEIGHT
        );

        ImageSupportUtils::savePngAtomically($resizedImage, self::getLogoPath());

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
