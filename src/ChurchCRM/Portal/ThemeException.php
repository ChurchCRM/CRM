<?php

namespace ChurchCRM\Portal;

use RuntimeException;

/**
 * Raised when a Member Portal theme cannot be used: its folder is gone, or
 * activation was refused because the validator found error-level problems.
 *
 * A broken theme fails loudly (design §3.3, decision P8) — this exception is
 * what carries the detail to the administrator's error page, never to a member.
 */
class ThemeException extends RuntimeException
{
    /**
     * @param array<int, array{file: string, line: int, message: string, level: string}> $findings
     */
    public function __construct(
        string $message,
        private readonly string $themeName = '',
        private readonly array $findings = []
    ) {
        parent::__construct($message);
    }

    public static function themeFolderNotFound(string $themeName): self
    {
        return new self(
            sprintf(gettext('The Member Portal theme folder "%s" was not found.'), $themeName),
            $themeName
        );
    }

    /**
     * @param array<int, array{file: string, line: int, message: string, level: string}> $errors
     */
    public static function activationRefused(string $themeName, array $errors): self
    {
        return new self(
            sprintf(gettext('The Member Portal theme "%s" could not be activated because it contains errors.'), $themeName),
            $themeName,
            $errors
        );
    }

    public function getThemeName(): string
    {
        return $this->themeName;
    }

    /**
     * @return array<int, array{file: string, line: int, message: string, level: string}>
     */
    public function getFindings(): array
    {
        return $this->findings;
    }
}
