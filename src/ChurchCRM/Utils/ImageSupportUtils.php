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
     * Build a GD image from raw bytes, scaled down to fit within
     * $maxWidth x $maxHeight. Aspect ratio is preserved and images smaller
     * than the box are never upscaled. Alpha is preserved so transparent
     * PNG/GIF sources survive the re-encode.
     *
     * @throws \Exception when the bytes are not a decodable image or GD fails
     */
    public static function createResizedImage(string $fileData, int $maxWidth, int $maxHeight): \GdImage
    {
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
}
