<?php

namespace ChurchCRM\Exceptions;

/**
 * A Volunteer v2 setup operation that failed for a reason the caller can act on:
 * a name that is already taken, a payload that does not hang together, a record
 * that is still referenced, a record named in the body that does not exist, or
 * an authorization decision.
 *
 * The service layer decides *what* went wrong; the route decides nothing but how
 * to render it. So the HTTP status travels with the exception rather than being
 * re-derived from the message in every handler (which is how error contracts
 * drift). `VolunteerSetupService` throws these; `volunteer-setup.php` catches
 * them and hands `getStatusCode()` straight to `SlimUtils::renderErrorJSON()`.
 *
 * Messages are wrapped in `gettext()` at the throw site, per design §3.4.
 */
class VolunteerSetupException extends \RuntimeException
{
    private readonly int $statusCode;

    public function __construct(string $message, int $statusCode = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /** The payload is malformed or internally inconsistent. */
    public static function invalid(string $message): self
    {
        return new self($message, 400);
    }

    /** The acting user holds no authority over the record (design §4.6). */
    public static function forbidden(string $message): self
    {
        return new self($message, 403);
    }

    /**
     * A record the payload named does not exist.
     *
     * Added with #9707, where it is the only way to answer honestly: a pool's
     * `vpol_OwnerId` is polymorphic and carries no foreign key (§2.5), so the
     * service is the only layer that can tell a caller their ministry or team
     * is not there. An entity middleware answers this for a record named in the
     * PATH; this covers one named in the BODY.
     */
    public static function notFound(string $message): self
    {
        return new self($message, 404);
    }

    /**
     * A uniqueness rule or a "still referenced, deactivate instead" rule refused
     * the write (design §2.3, §2.6).
     */
    public static function conflict(string $message): self
    {
        return new self($message, 409);
    }
}
