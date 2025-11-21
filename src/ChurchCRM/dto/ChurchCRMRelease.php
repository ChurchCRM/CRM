<?php

namespace ChurchCRM\dto;

class ChurchCRMRelease
{
    public int $MAJOR = 0;
    public int $MINOR = 0;
    public int $PATCH = 0;

    private array $rawRelease;

    public function __construct(array $releaseArray)
    {
        $this->rawRelease = $releaseArray;
        $versions = explode('.', $releaseArray['name']);
        
        // Convert to integers for proper numeric comparison
        $this->MAJOR = (int) ($versions[0] ?? 0);
        $this->MINOR = (int) ($versions[1] ?? 0);
        $this->PATCH = (int) ($versions[2] ?? 0);
    }

    public function equals(ChurchCRMRelease $b): bool
    {
        return $this->MAJOR === $b->MAJOR && $this->MINOR === $b->MINOR && $this->PATCH === $b->PATCH;
    }

    public function compareTo(ChurchCRMRelease $b): int
    {
        // Use version_compare() for proper semantic versioning support
        // Handles formats like X.Y.Z, X.Y.Z-alpha, X.Y.Z-rc1, etc.
        return version_compare($this->__toString(), $b->__toString());
    }

    public function __toString(): string
    {
        return $this->MAJOR . '.' . $this->MINOR . '.' . $this->PATCH;
    }

    public function getDownloadURL(): string
    {
        $expectedFileName = 'ChurchCRM-' . $this->rawRelease['name'] . '.zip';
        
        if (!isset($this->rawRelease['assets']) || !is_array($this->rawRelease['assets'])) {
            throw new \Exception('No assets found in release: ' . $this->rawRelease['name']);
        }
        
        foreach ($this->rawRelease['assets'] as $asset) {
            if (($asset['name'] ?? '') === $expectedFileName) {
                return $asset['browser_download_url'] ?? '';
            }
        }

        throw new \Exception('Download URL not found for ' . $expectedFileName);
    }

    public function getReleaseNotes(): string
    {
        return $this->rawRelease['body'] ?? '';
    }

    public function isPreRelease(): bool
    {
        return (bool) ($this->rawRelease['prerelease'] ?? false);
    }
}
