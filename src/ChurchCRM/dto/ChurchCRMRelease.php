<?php

namespace ChurchCRM\dto;

class ChurchCRMRelease
{
    public int $MAJOR = 0;
    public int $MINOR = 0;
    public int $PATCH = 0;

    private array $rawRelease;
    private string $versionString = '0.0.0';

    public function __construct(array $releaseArray)
    {
        $this->rawRelease = $releaseArray;

        $rawVersion = $releaseArray['tag_name'] ?? $releaseArray['name'] ?? '0.0.0';
        $normalizedVersion = ltrim(trim((string) $rawVersion), 'vV');

        if ($normalizedVersion === '') {
            $normalizedVersion = '0.0.0';
        }

        $this->versionString = $normalizedVersion;

        if (preg_match('/^(\d+)(?:\.(\d+))?(?:\.(\d+))?/', $normalizedVersion, $matches) === 1) {
            $this->MAJOR = (int) ($matches[1] ?? 0);
            $this->MINOR = (int) ($matches[2] ?? 0);
            $this->PATCH = (int) ($matches[3] ?? 0);
        }
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
