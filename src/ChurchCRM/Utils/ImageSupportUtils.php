<?php

namespace ChurchCRM\Utils;

use ChurchCRM\Exceptions\PhotoSizeException;
use ChurchCRM\Service\SystemService;

/**
 * ImageSupportUtils — Centralized image type support constants and utilities
 *
 * Manages allowed image formats across the application:
 * - Photo uploads (person/family avatars)
 * - Photo restoration (backup restore)
 * - PDF reports (FPDF rendering)
 *
 * Supported formats: JPEG, PNG, GIF, WebP (the formats our image pipeline handles)
 * Excluded: BMP, TIFF, SVG (see notes below)
 *
 * SVG is explicitly excluded due to stored-XSS risk: embedded <script> tags execute
 * in browsers when served as image/svg+xml by static file servers without PHP CSP headers.
 * See Photo.php and .htaccess for related security notes.
 *
 * BMP and TIFF are excluded because:
 * - FPDF (used in reports) only supports JPEG and PNG
 * - Image processing pipeline only handles the 5 core formats
 * - Supporting them adds no user benefit
 */
class ImageSupportUtils
{
    /**
     * Allowed file extensions (lowercase) for uploaded photos.
     * These are the formats that Photo.php can actually process and store.
     *
     * Note: new uploads are always saved as PNG (see Photo.php:256),
     * but we accept multiple formats from users for convenience.
     */
    public const ALLOWED_EXTENSIONS = ['png', 'jpeg', 'jpg', 'gif', 'webp'];

    /**
     * Allowed MIME types for uploaded photos.
     * Aligned with ALLOWED_EXTENSIONS.
     */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    /**
     * Map of file extensions to their canonical MIME type.
     * Used when finfo_open() is unavailable (fallback).
     */
    public const EXTENSION_MIME_MAP = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];

    /**
     * FPDF-supported image types (uppercase, as required by FPDF Image() method).
     * Used in PDF reports (PdfAttendance.php, etc).
     * FPDF does not support GIF or WebP, so we downgrade those to PNG.
     */
    public const FPDF_SUPPORTED_TYPES = ['JPG', 'JPEG', 'PNG'];

    /**
     * Hard ceiling on the number of source pixels an upload may carry,
     * whatever the server's memory limit. 50 megapixels covers every mainstream
     * camera (a 24 MP body shoots 6000x4000; a 50 MP body 8192x6144 is just
     * over and needs a resize first) while keeping the decoded raster in the
     * hundreds of megabytes at most.
     *
     * The compressed byte limit alone does not bound decoded memory: a valid
     * 12000x12000 PNG is about 140 KB on disk but 144 million pixels once GD
     * has decoded it. See createResizedImage().
     */
    public const MAX_SOURCE_PIXELS = 50_000_000;

    /**
     * Rough peak bytes GD needs per source pixel while decoding, by format.
     * The truecolor raster imagecreatefromstring() builds is 4 bytes/pixel.
     * libjpeg feeds it scanline by scanline, so JPEG peaks at about that.
     * libpng and libwebp first decode the whole image into their own buffer
     * (up to 4 bytes/pixel) and GD copies it into the raster, so those peak
     * at roughly twice. GIF decodes into a palette image (1 byte/pixel).
     */
    private const DECODE_BYTES_PER_PIXEL = [
        IMAGETYPE_JPEG => 4,
        IMAGETYPE_GIF  => 2,
        IMAGETYPE_PNG  => 8,
        IMAGETYPE_WEBP => 8,
    ];

    /** Bytes per pixel assumed for a format not listed above. */
    private const DEFAULT_DECODE_BYTES_PER_PIXEL = 8;

    /**
     * Share of the remaining memory_limit the source raster may take. The rest
     * is left for the resized copy, the PNG encoder and the remainder of the
     * request.
     */
    private const DECODE_MEMORY_HEADROOM = 0.8;

    /**
     * Check if a file extension is allowed.
     *
     * @param string $extension File extension (with or without leading dot)
     * @return bool
     */
    public static function isAllowedExtension(string $extension): bool
    {
        $ext = ltrim(strtolower($extension), '.');
        return \in_array($ext, self::ALLOWED_EXTENSIONS, true);
    }

    /**
     * Check if a MIME type is allowed.
     *
     * @param string $mimeType MIME type string (e.g. 'image/jpeg')
     * @return bool
     */
    public static function isAllowedMimeType(string $mimeType): bool
    {
        return \in_array($mimeType, self::ALLOWED_MIME_TYPES, true);
    }

    /**
     * Get the canonical MIME type for a given file extension.
     * Used when finfo_open() is unavailable.
     *
     * @param string $extension File extension (with or without leading dot)
     * @return string|null MIME type, or null if extension is not recognized
     */
    public static function getMimeTypeForExtension(string $extension): ?string
    {
        $ext = ltrim(strtolower($extension), '.');
        return self::EXTENSION_MIME_MAP[$ext] ?? null;
    }

    /**
     * Get the file extension that FPDF can handle for a given MIME type.
     * GIF and WebP are downgraded to PNG since FPDF only supports JPEG and PNG.
     *
     * @param string $mimeType MIME type string
     * @return string Uppercase extension suitable for FPDF, or 'PNG' as fallback
     */
    public static function getFpdfTypeForMimeType(string $mimeType): string
    {
        $typeMap = [
            'image/jpeg' => 'JPEG',
            'image/jpg'  => 'JPG',
            'image/png'  => 'PNG',
            'image/gif'  => 'PNG',      // Downgrade GIF to PNG for FPDF
            'image/webp' => 'PNG',      // Downgrade WebP to PNG for FPDF
        ];

        return $typeMap[$mimeType] ?? 'PNG'; // Default to PNG if unknown
    }

    /**
     * Get the file extension from a given MIME type (not uppercase for FPDF).
     *
     * @param string $mimeType MIME type string
     * @return string Lowercase extension, or 'png' as fallback
     */
    public static function getExtensionForMimeType(string $mimeType): string
    {
        $typeMap = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/webp' => 'webp',
        ];

        return $typeMap[$mimeType] ?? 'png';
    }

    /**
     * Decode a base64 image data URI and validate it against the allowed MIME
     * types and the effective server upload-size limit.
     *
     * Shared by every uploader that accepts an `imgBase64` body (person/family
     * photos, the church logo) so the allow-list, the SVG exclusion and the
     * size cap stay in one place.
     *
     * @param string $base64 A `data:<mime>;base64,<payload>` URI
     * @return string The decoded, validated raw image bytes
     * @throws \Exception when the URI, the base64 payload or the MIME type is invalid
     * @throws PhotoSizeException when the decoded image exceeds the server upload limit
     */
    public static function decodeBase64Image(string $base64): string
    {
        // Parse data URI with a single consistent pattern — handles all valid MIME subtypes
        // (including those with +, -, . such as image/svg+xml or image/vnd.ms-photo)
        if (!preg_match('/^data:([\w+.\/-]+);base64,(.+)$/s', $base64, $uriParts)) {
            throw new \Exception('Invalid image data: expected a base64-encoded data URI');
        }

        $uriMimeType = $uriParts[1];
        $fileData = base64_decode($uriParts[2], true);

        if ($fileData === false) {
            throw new \Exception('Invalid base64 data');
        }

        // Validate MIME type from binary content when fileinfo is available (preferred);
        // otherwise trust the data URI prefix — imagecreatefromstring() still enforces
        // the actual binary format, so non-images are rejected regardless.
        if (function_exists('finfo_open')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($fileData);
        } else {
            $mimeType = $uriMimeType;
        }

        if (!self::isAllowedMimeType($mimeType)) {
            throw new \Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP images are allowed.');
        }

        // Validate file size against the effective server limit (min of upload/post/memory)
        $maxSize = SystemService::getMaxUploadFileSize(false);
        if (strlen($fileData) > $maxSize) {
            throw new PhotoSizeException(
                sprintf('Image file size exceeds maximum allowed size of %s', SystemService::getMaxUploadFileSize(true))
            );
        }

        return $fileData;
    }

    /**
     * Number of source pixels an upload of the given type may carry on this
     * server: MAX_SOURCE_PIXELS, lowered when the memory left under
     * memory_limit could not hold the decoded raster. An unlimited
     * memory_limit (-1) leaves only the hard ceiling.
     *
     * @param int $imageType One of the IMAGETYPE_* constants from getimagesize()
     */
    public static function getDecodePixelBudget(int $imageType): int
    {
        $memoryLimit = self::parseIniBytes((string) ini_get('memory_limit'));
        if ($memoryLimit <= 0) {
            return self::MAX_SOURCE_PIXELS;
        }

        $bytesPerPixel = self::DECODE_BYTES_PER_PIXEL[$imageType] ?? self::DEFAULT_DECODE_BYTES_PER_PIXEL;
        $available = $memoryLimit - memory_get_usage(true);
        $memoryBudget = (int) floor($available * self::DECODE_MEMORY_HEADROOM / $bytesPerPixel);

        return max(0, min(self::MAX_SOURCE_PIXELS, $memoryBudget));
    }

    /**
     * Read the dimensions from the image header and refuse anything GD could
     * not decode within budget, before a single pixel is allocated.
     * getimagesizefromstring() only parses the header, so this costs nothing
     * for the 144-million-pixel PNG that would otherwise exhaust the worker.
     *
     * @return array{0:int,1:int,2:int} width, height and IMAGETYPE_* constant
     * @throws \Exception when the header cannot be read
     * @throws PhotoSizeException when the image is over the pixel budget
     */
    public static function assertWithinDecodeBudget(string $fileData): array
    {
        $info = @getimagesizefromstring($fileData);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            throw new \Exception('Failed to read the image dimensions from the uploaded data');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $type = (int) $info[2];
        $pixels = $width * $height;
        $budget = self::getDecodePixelBudget($type);

        if ($pixels > $budget) {
            // Whole pixel counts, not "144.0 megapixels": SlimUtils::renderErrorJSON()
            // replaces any message containing a dotted number (it looks like an IP
            // address to its credential filter) with a generic error.
            throw new PhotoSizeException(sprintf(
                'Image dimensions %dx%d (%s pixels) exceed the limit of %s pixels for uploads. Resize the image before uploading.',
                $width,
                $height,
                number_format($pixels),
                number_format($budget)
            ));
        }

        return [$width, $height, $type];
    }

    /**
     * Build a GD image from raw bytes, scaled down to fit within
     * $maxWidth x $maxHeight. Aspect ratio is preserved and images smaller
     * than the box are never upscaled. Alpha is preserved so transparent
     * PNG/GIF sources survive the re-encode.
     *
     * The source dimensions are checked against the decode budget first:
     * the output box bounds the stored image, not the memory needed to decode
     * the source, so an over-budget image is rejected before GD allocates.
     *
     * @throws \Exception when the bytes are not a decodable image or GD fails
     * @throws PhotoSizeException when the source is over the pixel budget
     */
    public static function createResizedImage(string $fileData, int $maxWidth, int $maxHeight): \GdImage
    {
        self::assertWithinDecodeBudget($fileData);

        $sourceImage = imagecreatefromstring($fileData);
        if ($sourceImage === false) {
            throw new \Exception('Failed to create image from uploaded data');
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        // Scale down to fit within the box, preserving aspect ratio.
        // Never upscale — images smaller than the max are stored at their natural size.
        $scale = min(1.0, $maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
        $destWidth = (int) round($sourceWidth * $scale);
        $destHeight = (int) round($sourceHeight * $scale);

        $resizedImage = imagecreatetruecolor($destWidth, $destHeight);
        if ($resizedImage === false) {
            throw new \Exception('Failed to create resized image');
        }

        // Preserve transparency for PNG/GIF
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);

        if (!imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0, 0, 0, 0,
            $destWidth,
            $destHeight,
            $sourceWidth,
            $sourceHeight
        )) {
            throw new \Exception('Failed to resize image');
        }

        return $resizedImage;
    }

    /**
     * Encode $image as PNG at $targetPath without ever exposing a partial file.
     *
     * The PNG is written to a unique temporary file in the same directory,
     * checked, then rename()d over the target, which is atomic on the same
     * filesystem. A reader (the web server, a browser fetching the logo)
     * therefore sees either the previous file or the complete new one, and a
     * failed encode or write leaves the previous file untouched. The temporary
     * file is removed on any failure.
     *
     * @throws \Exception when the temporary file cannot be created, the encode
     *                    fails, or the rename fails
     */
    public static function savePngAtomically(\GdImage $image, string $targetPath): void
    {
        $directory = dirname($targetPath);
        $tempPath = @tempnam($directory, basename($targetPath) . '.');

        // tempnam() silently falls back to the system temp dir when $directory
        // is not writable; a rename from there would not be atomic (and would
        // fail anyway), so treat it as the failure it is.
        if ($tempPath === false || realpath(dirname($tempPath)) !== realpath($directory)) {
            if ($tempPath !== false) {
                @unlink($tempPath);
            }
            throw new \Exception('Failed to create a temporary file in the image directory');
        }

        $replaced = false;
        try {
            if (!imagepng($image, $tempPath)) {
                throw new \Exception('Failed to encode the image as PNG');
            }

            clearstatcache(true, $tempPath);
            if ((int) @filesize($tempPath) === 0) {
                throw new \Exception('The encoded image is empty');
            }

            // tempnam() creates the file 0600; give it the permissions a plain
            // imagepng() write would have so the web server can serve it.
            @chmod($tempPath, 0644);

            if (!@rename($tempPath, $targetPath)) {
                throw new \Exception('Failed to replace the stored image');
            }
            $replaced = true;
        } finally {
            if (!$replaced) {
                @unlink($tempPath);
            }
            clearstatcache(true, $targetPath);
        }
    }

    /**
     * Parse a php.ini shorthand size ("128M", "2G", "-1") into bytes.
     * Returns a non-positive number for "unlimited" values.
     */
    private static function parseIniBytes(string $size): int
    {
        $size = trim($size);
        if ($size === '') {
            return 0;
        }

        $value = (int) $size;
        switch (strtolower(substr($size, -1))) {
            case 'g':
                $value *= 1024;
                // fallthrough
            case 'm':
                $value *= 1024;
                // fallthrough
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
