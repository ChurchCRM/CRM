<?php

namespace ChurchCRM\dto;

use ChurchCRM\Exceptions\PhotoSizeException;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Service\SystemService;

/**
 * Photo class handles uploaded photos for Person and Family entities.
 * 
 * This class ONLY handles uploaded photos. Avatar generation (initials, gravatar)
 * is handled client-side using the avatar-initials npm package.
 * 
 * @see webpack/avatar-loader.ts for client-side avatar rendering
 */
class Photo
{
    // Hardcoded photo dimensions - all photos stored at this size for optimal bandwidth/storage
    public const PHOTO_WIDTH = 200;
    public const PHOTO_HEIGHT = 200;
    
    // HTTP cache duration for photo responses (in seconds)
    public const CACHE_DURATION_SECONDS = 7200; // 2 hours

    private string $photoType;
    private int $id;
    private ?string $photoURI = null;
    private ?string $photoContentType = null;
    private bool $hasUploadedPhoto = false;

    public static array $validExtensions = ['png', 'jpeg', 'jpg', 'gif', 'webp'];

    public function __construct(string $photoType, int $id)
    {
        $this->photoType = $photoType;
        $this->id = $id;
        $this->findUploadedPhoto();
    }

    public static function getValidExtensions(): array
    {
        return self::$validExtensions;
    }

    /**
     * Check if this entity has an uploaded photo
     */
    public function hasUploadedPhoto(): bool
    {
        return $this->hasUploadedPhoto;
    }

    /**
     * Look for an uploaded photo file (no remote or initials)
     */
    private function findUploadedPhoto(): void
    {
        $this->ensurePhotoDirsExist();
        
        $baseName = SystemURLs::getImagesRoot() . '/' . $this->photoType . '/' . $this->id;

        foreach (self::$validExtensions as $ext) {
            $photoFile = $baseName . '.' . $ext;
            if (is_file($photoFile)) {
                $this->photoURI = $photoFile;
                $this->hasUploadedPhoto = true;
                return;
            }
        }
        
        // No uploaded photo found
        $this->hasUploadedPhoto = false;
        $this->photoURI = null;
    }

    /**
     * Ensure required photo directories exist
     */
    private function ensurePhotoDirsExist(): void
    {
        $imagesRoot = SystemURLs::getImagesRoot();
        $photoTypeDir = $imagesRoot . '/' . $this->photoType;
        
        if (!is_dir($imagesRoot)) {
            @mkdir($imagesRoot, 0755, true);
        }
        
        if (!is_dir($photoTypeDir)) {
            @mkdir($photoTypeDir, 0755, true);
        }
    }

    /**
     * Get photo bytes - only for uploaded photos
     * 
     * @throws \Exception if no uploaded photo exists
     */
    public function getPhotoBytes(): string
    {
        if (!$this->hasUploadedPhoto || !$this->photoURI) {
            throw new \Exception('No uploaded photo exists for this entity');
        }
        
        if (!file_exists($this->photoURI)) {
            throw new \Exception("Photo file not found: " . $this->photoURI);
        }
        
        $content = file_get_contents($this->photoURI);
        if ($content === false) {
            throw new \Exception("Failed to read photo file: " . $this->photoURI);
        }

        return $content;
    }

    public function getPhotoContentType(): ?string
    {
        if (!$this->hasUploadedPhoto || !$this->photoURI) {
            return null;
        }

        if (function_exists('finfo_open')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $this->photoContentType = $finfo->file($this->photoURI);
        } else {
            // Fallback: derive from file extension (all new uploads are saved as .png)
            $ext = strtolower(pathinfo($this->photoURI, PATHINFO_EXTENSION));
            $mimeMap = [
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
            ];
            $this->photoContentType = $mimeMap[$ext] ?? null;
        }

        return $this->photoContentType;
    }

    public function getPhotoURI(): ?string
    {
        return $this->photoURI;
    }

    /**
     * Return the last-modified timestamp of the uploaded photo file, or 0 if
     * no photo exists. Useful as a cache-busting version token for <img src>
     * so that re-uploading the same file URL produces a fresh browser fetch.
     * See #8662.
     */
    public function getPhotoModifiedTime(): int
    {
        if (!$this->hasUploadedPhoto || !$this->photoURI || !is_file($this->photoURI)) {
            return 0;
        }

        $mtime = @filemtime($this->photoURI);
        return $mtime === false ? 0 : $mtime;
    }

    /**
     * Save an uploaded image from base64 data
     */
    public function setImageFromBase64(string $base64): void
    {
        $this->ensurePhotoDirsExist();

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
        
        // Allowed image types
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        if (!isset($allowedMimeTypes[$mimeType])) {
            throw new \Exception('Invalid image type. Only JPEG, PNG, GIF, and WebP images are allowed.');
        }
        
        // Validate file size against the effective server limit (min of upload/post/memory)
        $maxSize = SystemService::getMaxUploadFileSize(false);
        if (strlen($fileData) > $maxSize) {
            throw new PhotoSizeException(
                sprintf('Image file size exceeds maximum allowed size of %s', SystemService::getMaxUploadFileSize(true))
            );
        }
        
        // Create GD image from uploaded data
        $sourceImage = imagecreatefromstring($fileData);
        if ($sourceImage === false) {
            throw new \Exception('Failed to create image from uploaded data');
        }
        
        // Get original dimensions
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);
        
        // Create resized image at standard dimensions
        $resizedImage = imagecreatetruecolor(self::PHOTO_WIDTH, self::PHOTO_HEIGHT);
        if ($resizedImage === false) {
            throw new \Exception('Failed to create resized image');
        }
        
        // Preserve transparency for PNG/GIF
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        
        // Resize image to standard dimensions
        if (!imagecopyresampled(
            $resizedImage,
            $sourceImage,
            0, 0, 0, 0,
            self::PHOTO_WIDTH,
            self::PHOTO_HEIGHT,
            $sourceWidth,
            $sourceHeight
        )) {
            throw new \Exception('Failed to resize image');
        }
        
        // Delete any existing photo first
        $this->delete();
        
        // Save as PNG at standard dimensions
        $fileName = SystemURLs::getImagesRoot() . '/' . $this->photoType . '/' . $this->id . '.png';
        
        if (!imagepng($resizedImage, $fileName)) {
            throw new \Exception('Failed to save resized image');
        }
        
        // Update state
        $this->photoURI = $fileName;
        $this->hasUploadedPhoto = true;
    }
    
    /**
     * Parse size string (e.g., "8M", "2G") to bytes
     */
    private function parseSize(string $size): int
    {
        $unit = strtolower(substr($size, -1));
        $value = (int)$size;
        
        switch ($unit) {
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

    /**
     * Delete the uploaded photo and any legacy remote/initials files
     */
    public function delete(): bool
    {
        // Delete main photo
        if ($this->photoURI && is_file($this->photoURI)) {
            unlink($this->photoURI);
        }
        
        // Also clean up any legacy remote/initials files
        $baseName = SystemURLs::getImagesRoot() . '/' . $this->photoType . '/' . $this->id;
        $legacyPatterns = ['-remote.png', '-initials.png', '-remote.jpg', '-initials.jpg'];
        foreach ($legacyPatterns as $pattern) {
            $legacyFile = $baseName . $pattern;
            if (is_file($legacyFile)) {
                unlink($legacyFile);
            }
        }
        
        $this->photoURI = null;
        $this->hasUploadedPhoto = false;
        
        return true;
    }

    /**
     * Refresh photo state (re-check for uploaded photo)
     */
    public function refresh(): void
    {
        $this->findUploadedPhoto();
    }

    // ========== Static Helper Methods for Avatar Info ==========

    /**
     * Get complete avatar info for a Person (single DB query)
     */
    private static function getPersonAvatarInfo(int $personId): array
    {
        $person = PersonQuery::create()->findOneById($personId);
        
        if ($person === null) {
            return [
                'initials' => '?',
                'email' => null,
            ];
        }
        
        $style = SystemConfig::getIntValue('iPersonInitialStyle');
        $email = $person->getEmail();
        
        return [
            'initials' => $person->getInitial($style),
            'email' => !empty($email) ? $email : null,
        ];
    }

    /**
     * Get complete avatar info for a Family (single DB query for family, possible additional for heads)
     */
    private static function getFamilyAvatarInfo(int $familyId): array
    {
        $family = FamilyQuery::create()->findOneById($familyId);
        
        if ($family === null) {
            return [
                'initials' => '?',
                'email' => null,
            ];
        }
        
        // Get initials from family name (first two characters)
        $name = trim($family->getName());
        // Handle edge cases: empty name, single character, special characters
        if ($name === '') {
            $initials = '?';
        } else {
            // Take up to 2 characters for better readability
            $initials = mb_strtoupper(mb_substr($name, 0, min(2, mb_strlen($name))));
        }
        
        // Try to get email from head of household first
        // NOTE: getHeadPeople() may trigger additional queries if not eagerly loaded.
        // For bulk operations, consider optimizing with joinWith() in the query.
        $email = null;
        $heads = $family->getHeadPeople();
        foreach ($heads as $head) {
            $headEmail = $head->getEmail();
            if (!empty($headEmail)) {
                $email = $headEmail;
                break;
            }
        }
        
        // Fall back to family email if no head has email
        if ($email === null) {
            $familyEmail = $family->getEmail();
            $email = !empty($familyEmail) ? $familyEmail : null;
        }
        
        return [
            'initials' => $initials,
            'email' => $email,
        ];
    }

    /**
     * Get complete avatar info for an entity (used by API)
     * Optimized to make minimal DB queries
     */
    public static function getAvatarInfo(string $entityType, int $entityId): array
    {
        $photo = new self($entityType, $entityId);
        
        // Get entity-specific info with single query per entity type
        $entityInfo = match ($entityType) {
            'Person' => self::getPersonAvatarInfo($entityId),
            'Family' => self::getFamilyAvatarInfo($entityId),
            default => ['initials' => '?', 'email' => null],
        };
        
        return [
            'hasPhoto' => $photo->hasUploadedPhoto(),
            'photoUrl' => null,
            'initials' => $entityInfo['initials'],
            'email' => $entityInfo['email'],
        ];
    }
}
