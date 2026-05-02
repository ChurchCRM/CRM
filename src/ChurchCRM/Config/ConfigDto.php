<?php

namespace ChurchCRM\Config;

/**
 * Data Transfer Object for configuration values.
 * Provides type-safe access to ChurchCRM configuration.
 */
final class ConfigDto
{
    /** @param string[] $urls All URLs from Config.php — $URL[0] is primary; $URL[1..n] are alternates */
    public function __construct(
        public readonly string $dbServerName,
        public readonly string $dbServerPort,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $rootPath,
        public readonly array  $urls,
    ) {}

    public function getDbServerName(): string
    {
        return $this->dbServerName;
    }

    public function getDbServerPort(): string
    {
        return $this->dbServerPort;
    }

    public function getDbName(): string
    {
        return $this->dbName;
    }

    public function getDbUser(): string
    {
        return $this->dbUser;
    }

    public function getDbPassword(): string
    {
        return $this->dbPassword;
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    /** Primary URL ($URL[0]) */
    public function getUrl(): string
    {
        return $this->urls[0];
    }

    /** Full URL array — primary + any configured alternates */
    public function getUrls(): array
    {
        return $this->urls;
    }
}
