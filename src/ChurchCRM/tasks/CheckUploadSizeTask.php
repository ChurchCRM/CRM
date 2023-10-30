<?php


namespace ChurchCRM\Tasks;

use ChurchCRM\dto\SystemURLs;

class CheckUploadSizeTask
{
    private $sizeString;
    private $sizeBytes;

    public function __construct()
    {
        $this->sizeString = ini_get('upload_max_filesize');
        $this->sizeBytes = self::return_bytes($this->sizeString);
    }

    public function isActive(): bool
    {
        return $this->sizeBytes < self::return_bytes('5M');
    }

    public function isAdmin(): bool
    {
        return true;
    }

    public function getLink(): string
    {
        return SystemURLs::getSupportURL(array_pop(explode('\\', self::class)));
    }

    public function getTitle(): string
    {
        return gettext('PHP Max File size too small') . " (" . $this->sizeString . ")";
    }

    public function getDesc(): string
    {
        return gettext("Increase the php upload limits to allow for mobile photo upload, and backup restore.");
    }

    static function return_bytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = substr($val, 0, -1);
        switch ($last) {
            case 'g':
                $val *= 1_073_741_824;
            case 'm':
                $val *= 1_048_576;
            case 'k':
                $val *= 1024;
        }
        return $val;
    }
}
