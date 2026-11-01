<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Exceptions\PhotoSizeException;
use ChurchCRM\Utils\ImageSupportUtils;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Stores and removes the single letterhead image that PDF letters print at the top.
 * The upload pipeline is shared with the church logo through `ImageSupportUtils`
 * and the result is always re-encoded to PNG. `MpdfRenderer::graphicLetterhead()`
 * decides between this file and the `bDirLetterHead` path.
 */
class LetterheadService
{
    /** File name of the uploaded letterhead, relative to the Images directory. */
    public const FILENAME = 'church-letterhead.png';

    /** Uploads are scaled down to fit this box; smaller images are never upscaled. */
    public const MAX_WIDTH = 2400;
    public const MAX_HEIGHT = 600;

    public static function getPath(): string
    {
        return SystemURLs::getImagesRoot() . '/' . self::FILENAME;
    }

    public static function hasUploaded(): bool
    {
        return is_file(self::getPath());
    }

    /** URL relative to the application root with a content-hash token, or null when none is stored. */
    public static function getUrlPath(): ?string
    {
        $hash = self::hasUploaded() ? hash_file('xxh3', self::getPath()) : false;

        return $hash === false ? null : '/Images/' . self::FILENAME . '?v=' . $hash;
    }

    /**
     * @throws \Exception when the payload is not a supported image
     * @throws PhotoSizeException when it exceeds the server upload limit or the source image size limit
     */
    public static function setImageFromBase64(string $base64): void
    {
        $imagesRoot = SystemURLs::getImagesRoot();
        if (!is_dir($imagesRoot)) {
            @mkdir($imagesRoot, 0755, true);
        }

        $image = ImageSupportUtils::createResizedImage(
            ImageSupportUtils::decodeBase64Image($base64),
            self::MAX_WIDTH,
            self::MAX_HEIGHT
        );
        ImageSupportUtils::savePngAtomically($image, self::getPath());

        LoggerUtils::getAppLogger()->info('Letterhead uploaded', [
            'width' => imagesx($image),
            'height' => imagesy($image),
        ]);
    }

    /** Idempotent: true when no letterhead is stored. */
    public static function delete(): bool
    {
        if (!self::hasUploaded()) {
            return true;
        }

        $deleted = @unlink(self::getPath());
        if (!$deleted) {
            LoggerUtils::getAppLogger()->error('Failed to remove letterhead', ['path' => self::getPath()]);
        }

        return $deleted;
    }
}
