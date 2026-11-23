<?php

namespace ChurchCRM\Utils;

use ChurchCRM\dto\SystemConfig;
use Ramsey\Uuid\Uuid;

/**
 * The installation's identity for its whole life, kept in Include/SystemID.php so it is readable while the
 * database is missing, unreachable or mid-upgrade. The file is canonical; sSystemID mirrors it. A .php file
 * that only returns the ID, so a direct web request prints nothing.
 */
final class SystemIdentity
{
    private const ID_PATTERN = '[A-Za-z0-9-]{1,64}';

    private static ?string $path = null;

    public static function read(): ?string
    {
        $contents = @file_get_contents(self::path());

        return is_string($contents) && preg_match("/'(" . self::ID_PATTERN . ")'/", $contents, $match) === 1 ? $match[1] : null;
    }

    /**
     * The identity from the file. A missing file is written from $knownId (the database copy), or from a new
     * UUID before Config.php exists. A configured install without a known ID writes nothing: its database
     * holds the identity, and a new one would split its history. Null when there is nothing to return.
     */
    public static function ensure(string $knownId = ''): ?string
    {
        $id = self::read();
        if ($id !== null) {
            return $id;
        }
        $isKnown = preg_match('/^' . self::ID_PATTERN . '$/', $knownId) === 1;
        if (!$isKnown && file_exists(dirname(self::path()) . '/Config.php')) {
            return null;
        }
        // Mode 'x': when two first requests race, the one that created the file decides the identity.
        $handle = @fopen(self::path(), 'x');
        if ($handle !== false) {
            fwrite($handle, self::contents($isKnown ? $knownId : Uuid::uuid4()->toString()));
            fclose($handle);
        }

        return self::read() ?? ($isKnown ? $knownId : null);
    }

    /** A restored database brings back its installation, so the file takes that installation's ID. */
    public static function adopt(string $id): void
    {
        if (preg_match('/^' . self::ID_PATTERN . '$/', $id) === 1 && $id !== self::read()) {
            @file_put_contents(self::path(), self::contents($id), LOCK_EX);
        }
    }

    private static function contents(string $id): string
    {
        return "<?php\n\nreturn '" . $id . "';\n";
    }

    /** The identity to show or hand to the browser: the file, else the database copy. */
    public static function current(): string
    {
        return self::read() ?? (string) SystemConfig::getValue('sSystemID');
    }

    private static function path(): string
    {
        return self::$path ?? dirname(__DIR__, 2) . '/Include/SystemID.php';
    }
}
