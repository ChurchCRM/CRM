<?php

namespace ChurchCRM\dto;

class ChurchCRMRelease
{
    public int $MAJOR = 0;
    public int $MINOR = 0;
    public int $PATCH = 0;

    private array $rawRelease;
    private string $versionString = '0.0.0';
    private bool $hasVersion = false;

    private const VERSION_PATTERN = '/^\d+\.\d+\.\d+/';

    public function __construct(array $releaseArray)
    {
        $this->rawRelease = $releaseArray;

        // A placeholder tag (e.g. "untagged-c80e...") must not hide a good release name (#10096)
        $normalizedVersion = '0.0.0';
        foreach ([$releaseArray['tag_name'] ?? null, $releaseArray['name'] ?? null] as $candidate) {
            $candidate = ltrim(trim((string) $candidate), 'vV');
            if (preg_match(self::VERSION_PATTERN, $candidate) === 1) {
                $normalizedVersion = $candidate;
                $this->hasVersion = true;
                break;
            }
        }

        $this->versionString = $normalizedVersion;

        if (preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $normalizedVersion, $matches) === 1) {
            $this->MAJOR = (int) ($matches[1] ?? 0);
            $this->MINOR = (int) ($matches[2] ?? 0);
            $this->PATCH = (int) ($matches[3] ?? 0);
        }
    }

    public function hasVersion(): bool
    {
        return $this->hasVersion;
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
        return $this->versionString;
    }

    public function getDownloadURL(): string
    {
        $expectedFileNames = [];

        if (isset($this->rawRelease['name'])) {
            $expectedFileNames[] = 'ChurchCRM-' . $this->rawRelease['name'] . '.zip';
        }

        if (isset($this->rawRelease['tag_name'])) {
            $expectedFileNames[] = 'ChurchCRM-' . ltrim((string) $this->rawRelease['tag_name'], 'vV') . '.zip';
            $expectedFileNames[] = 'ChurchCRM-' . $this->rawRelease['tag_name'] . '.zip';
        }

        $expectedFileNames[] = 'ChurchCRM-' . $this->__toString() . '.zip';

        if (!isset($this->rawRelease['assets']) || !is_array($this->rawRelease['assets'])) {
            throw new \Exception('No assets found in release: ' . $this->rawRelease['name']);
        }

        foreach ($this->rawRelease['assets'] as $asset) {
            $assetName = $asset['name'] ?? '';

            foreach ($expectedFileNames as $expectedFileName) {
                if ($assetName === $expectedFileName) {
                    return $asset['browser_download_url'] ?? '';
                }
            }
        }

        throw new \Exception('Download URL not found for ChurchCRM release ' . $this->__toString());
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
