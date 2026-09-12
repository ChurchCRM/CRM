<?php

namespace ChurchCRM\Exceptions;

/**
 * A Volunteer v2 operation that failed for a reason the caller can act on: a name
 * that is already taken, a payload that does not hang together, a record that is
 * still referenced, a record named in the body that does not exist, an invariant
 * the workflow refuses to break, or an authorization decision.
 *
 * The service layer decides *what* went wrong; the route decides nothing but how
 * to render it. So the HTTP status travels with the exception rather than being
 * re-derived from the message in every handler (which is how error contracts
 * drift). `VolunteerSetupService` (#9707/#9715) and `VolunteerAssignmentService`
 * (#9709) both throw these; their route files catch them and hand
 * `getStatusCode()` straight to `SlimUtils::renderErrorJSON()`.
 *
 * The class keeps its original name rather than gaining a near-identical sibling
 * for the workflow half: one exception type carrying one status contract is what
 * keeps the V2 error shape from splitting in two.
 *
 * `extra` (#9709) is the small structured payload some errors must carry beyond a
 * sentence. §2.11.1 requires an illegal assignment transition to answer `409`
 * **with the current status in the body**, and a client that wants to re-render a
 * row needs that as a field, not as prose it would have to parse. It is merged
 * into the JSON envelope by `renderErrorJSON()`'s `$extra` parameter.
 *
 * Messages are wrapped in `gettext()` at the throw site, per design §3.4.
 */
class VolunteerSetupException extends \RuntimeException
{
    private readonly int $statusCode;

    /** @var array<string, mixed> */
    private array $extra = [];

    public function __construct(string $message, int $statusCode = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Attach structured fields to the error envelope. Fluent so a throw site stays
     * one expression: `throw VolunteerSetupException::conflict($msg)->withExtra([...])`.
     *
     * @param array<string, mixed> $extra
     */
    public function withExtra(array $extra): self
    {
        $this->extra = $extra;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getExtra(): array
    {
        return $this->extra;
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
