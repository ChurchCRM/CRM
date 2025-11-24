<?php

namespace ChurchCRM\dto;

use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\MiscUtils;

class Photo
{
    // Hardcoded photo dimensions - all photos stored at this size for optimal bandwidth/storage
    public const PHOTO_WIDTH = 200;
    public const PHOTO_HEIGHT = 200;
    public const INITIALS_FONT_SIZE = 75;
    
    // HTTP cache duration for photo responses (in seconds)
    public const CACHE_DURATION_SECONDS = 7200; // 2 hours

    private string $photoType;
    private int $id;
    private $photoURI;
    private $photoContentType = null;
    private bool $remotesEnabled;

    public static $validExtensions = ['png', 'jpeg', 'jpg', 'gif', 'webp'];

    public function __construct(string $photoType, int $id)
    {
        $this->photoType = $photoType;
        $this->id = $id;
        $this->remotesEnabled = SystemConfig::getBooleanValue('bEnableGravatarPhotos');
        $this->photoHunt();
    }

    public static function getValidExtensions()
    {
        return Photo::$validExtensions;
    }

    private function setURIs(string $photoPath): void
    {
        $this->photoURI = $photoPath;
    }

    private function shouldRefreshPhotoFile(string $photoFile): bool
    {
        $logger = LoggerUtils::getAppLogger();

        if (!$this->remotesEnabled) {
            // if remotes are disabled, and the image contains remote, then we should re-gen
            return strpos($photoFile, 'remote') !== false;
        }

        // default defined in SystemConfig.php
        $defaultInterval = \DateInterval::createFromDateString('72 hours');

        $interval = null;
        try {
            // if the system has remotes enabled, calculate the cutoff timestamp for refreshing remote photos.
            $remotePhotoCacheDuration = SystemConfig::getValue('iRemotePhotoCacheDuration');
            if (!$remotePhotoCacheDuration) {
                // log error and use default value
                $logger->error(
                    'config iRemotePhotoCacheDuration somehow not set, please investigate',
                    ['stacktrace' => debug_backtrace()]
                );
            } else {
                $interval = \DateInterval::createFromDateString($remotePhotoCacheDuration);
                MiscUtils::throwIfFailed($interval);
            }
        } catch (\Throwable $exception) {
            // log error and use default value
            $logger->error(
                'invalid config provided for iRemotePhotoCacheDuration',
                [
                    'iRemotePhotoCacheDuration' => SystemConfig::getValue('iRemotePhotoCacheDuration'),
                    'exception' => $exception,
                ]
            );
        }

        if ($interval === null) {
            $interval = $defaultInterval;
        }
        $remoteCacheThreshold = new \DateTimeImmutable();
        $remoteCacheThreshold = $remoteCacheThreshold->sub($interval);

        if (strpos($photoFile, 'remote') !== false || strpos($photoFile, 'initials') !== false) {
            return filemtime($photoFile) < $remoteCacheThreshold->getTimestamp();
        }

        return false;
    }

    private function photoHunt(): void
    {
        // Ensure directories exist
        $this->ensurePhotoDirsExist();
        
        $baseName = SystemURLs::getImagesRoot() . '/' . $this->photoType . '/' . $this->id;
        $extensions = Photo::$validExtensions;

        foreach ($extensions as $ext) {
            $photoFiles = [$baseName . '.' . $ext, $baseName . '-remote.' . $ext, $baseName . '-initials.' . $ext];
            foreach ($photoFiles as $photoFile) {
                if (is_file($photoFile)) {
                    $this->setURIs($photoFile);
                    if ($ext !== 'png') {
                        $this->convertToPNG();
                    }
                    if ($this->shouldRefreshPhotoFile($photoFile)) {
                        //if we found the file, but it's remote and aged, then we should update it.
                        $this->delete();
                        break 2;
                    }

                    return;
                }
            }
        }
        // we still haven't found a photo file.  Begin checking remote if it's enabled
        // only check google and gravatar for person photos.
        if ($this->photoType == 'Person' && $this->remotesEnabled) {
            $person = PersonQuery::create()->findOneById($this->id);
            if ($person) {
                $personEmail = $person->getEmail();
                if (SystemConfig::getBooleanValue('bEnableGravatarPhotos')) {
                    try {
                        $photoPath = $this->loadFromGravatar($personEmail, $baseName);
                        $this->setURIs($photoPath);

                        return;
                    } catch (\Exception $e) {
                        // do nothing
                    }
                }
            }
        }

        // still no image - generate it from initials
        $this->renderInitials();
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

    private function convertToPNG(): void
    {
        $image = $this->getGDImage($this->getPhotoURI());
        $this->delete();
        $targetPath = SystemURLs::getImagesRoot() . '/' . $this->photoType . '/' . $this->id . '.png';
        imagepng($image, $targetPath);
        $this->setURIs($targetPath);
    }

    private function getGDImage($sourceImagePath): \GdImage
    {
        $sourceImageType = exif_imagetype($sourceImagePath);
        switch ($sourceImageType) {
            case IMAGETYPE_GIF:
                $sourceGDImage = imagecreatefromgif($sourceImagePath);
                break;
            case IMAGETYPE_JPEG:
                $sourceGDImage = imagecreatefromjpeg($sourceImagePath);
                break;
            case IMAGETYPE_PNG:
                $sourceGDImage = imagecreatefrompng($sourceImagePath);
                break;
            default:
                throw new \Exception('Unsupported image type: ' . $sourceImageType);
        }
        MiscUtils::throwIfFailed($sourceGDImage);

        return $sourceGDImage;
    }

    public function getPhotoBytes(): string
    {
        if (!file_exists($this->photoURI)) {
            // Return a placeholder image or throw a more helpful error
            throw new \Exception("Photo file not found: " . $this->photoURI);
        }
        
        $content = file_get_contents($this->photoURI);
        if ($content === false) {
            throw new \Exception("Failed to read photo file: " . $this->photoURI);
        }

        return (string) $content;
    }

    public function getPhotoContentType()
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $this->photoContentType = $finfo->file($this->photoURI);

        return $this->photoContentType;
    }

    public function getPhotoURI()
    {
        return $this->photoURI;
    }

    private function loadFromGravatar($email, string $baseName): string
    {
        $s = 60;
        $d = '404';
        $r = 'g';
        $url = 'https://www.gravatar.com/avatar/';
        $url .= md5(strtolower(trim($email)));
        $url .= "?s=$s&d=$d&r=$r";

        $photo = imagecreatefromstring(file_get_contents($url));
        if ($photo) {
            $photoPath = $baseName . '-remote.png';
            imagepng($photo, $photoPath);

            return $photoPath;
        }

        throw new \Exception('Gravatar not found');
    }

    

    private function getRandomColor(\GdImage $image)
    {
        $red = random_int(0, 150);
        $green = random_int(0, 150);
        $blue = random_int(0, 150);

        return imagecolorallocate($image, $red, $green, $blue);
    }

    private function getInitialsString(): string
    {
        $retstr = '';
        if ($this->photoType == 'Person') {
            $retstr = PersonQuery::create()->findOneById($this->id)->getInitial(SystemConfig::getValue('iPersonInitialStyle'));
        } elseif ($this->photoType == 'Family') {
            $fullNameArr = FamilyQuery::create()->findOneById($this->id)->getName();
            $retstr .= mb_strtoupper(mb_substr($fullNameArr, 0, 1));
        }

        return $retstr;
    }

    private function renderInitials(): void
    {
        $initials = $this->getInitialsString();
        $targetPath = SystemURLs::getImagesRoot() . '/' . $this->photoType . '/' . $this->id . '-initials.png';
        $font = SystemURLs::getDocumentRoot() . '/fonts/' . SystemConfig::getValue('sFont');
        $image = imagecreatetruecolor(self::PHOTO_WIDTH, self::PHOTO_HEIGHT);
        MiscUtils::throwIfFailed($image);
        $bgcolor = $this->getRandomColor($image);
        $white = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, self::PHOTO_HEIGHT, self::PHOTO_WIDTH, $bgcolor);
        $tb = imageftbbox(self::INITIALS_FONT_SIZE, 0, $font, $initials);
        $x = ceil((self::PHOTO_WIDTH - $tb[2]) / 2);
        $y = ceil((self::PHOTO_HEIGHT - $tb[5]) / 2);
        imagefttext($image, self::INITIALS_FONT_SIZE, 0, $x, $y, $white, $font, $initials);
        imagepng($image, $targetPath);
        $this->setURIs($targetPath);
    }

    public function setImageFromBase64($base64): void
    {
        $this->delete();
        
        // Extract mime type and base64 data
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64, $matches)) {
            $imageType = $matches[1];
            $base64Data = $matches[2];
        } else {
            // Fallback for legacy format without data URI prefix
            $imageType = 'png';
            $base64Data = str_replace(' ', '+', $base64);
            $base64Data = str_replace('data:image/png;base64,', '', $base64Data);
        }
        
        // Decode base64 data
        $fileData = base64_decode($base64Data);
        if ($fileData === false) {
            throw new \Exception('Invalid base64 data');
        }
        
        // Validate file is actually an image using finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($fileData);
        
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
        
        // Validate file size (check against max upload size)
        $maxSize = $this->parseSize(ini_get('upload_max_filesize'));
        if (strlen($fileData) > $maxSize) {
            throw new \Exception('Image file size exceeds maximum allowed size');
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
            imagedestroy($sourceImage);
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
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            throw new \Exception('Failed to resize image');
        }
        
        // Save as PNG at standard dimensions
        $fileName = SystemURLs::getImagesRoot() . '/' . $this->photoType . '/' . $this->id . '.png';
        
        if (!imagepng($resizedImage, $fileName)) {
            imagedestroy($sourceImage);
            imagedestroy($resizedImage);
            throw new \Exception('Failed to save resized image');
        }
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);
        
        // Update URIs
        $this->setURIs($fileName);
    }
    
    /**
     * Parse size string (e.g., "8M", "2G") to bytes
     */
    private function parseSize($size): int
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

    public function delete(): bool
    {
        if ($this->photoURI && is_file($this->photoURI)) {
            return unlink($this->photoURI);
        }

        return false;
    }

    public function refresh(): void
    {
        if (strpos($this->photoURI, 'initials') || strpos($this->photoURI, 'remote')) {
            $this->delete();
        }
        $this->photoHunt();
    }

    public function isInitials(): bool
    {
        return strpos($this->photoURI, 'initials') !== false;
    }
}
