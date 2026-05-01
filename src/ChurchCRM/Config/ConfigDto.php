<?php

namespace ChurchCRM\Config;

/**
 * Data Transfer Object for configuration values.
 * Provides type-safe access to ChurchCRM configuration.
 */
final class ConfigDto
{
    public function __construct(
        public readonly string $dbServerName,
        public readonly string $dbServerPort,
        public readonly string $dbName,
        public readonly string $dbUser,
        public readonly string $dbPassword,
        public readonly string $rootPath,
        public readonly string $url,
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

    public function getUrl(): string
    {
        return $this->url;
    }
}
