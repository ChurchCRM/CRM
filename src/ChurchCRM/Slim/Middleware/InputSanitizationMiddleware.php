<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Sanitizes and validates request body fields before passing to the route handler.
 *
 * Fields are sanitized in-place; only fields that are present in the body
 * are affected. Missing fields are left absent (not set to empty string).
 *
 * Supported sanitization types:
 *  - 'text'     → InputUtils::sanitizeText() (trims and strips HTML tags)
 *  - 'html'     → InputUtils::sanitizeHTML() (allows safe HTML, strips scripts)
 *  - 'int'      → filter_var(FILTER_VALIDATE_INT) — field MUST be present and a valid integer;
 *                 returns HTTP 400 if absent or not a valid integer
 *  - 'date'     → strict `YYYY-MM-DD`, parsed with \DateTimeImmutable::createFromFormat('!Y-m-d', …)
 *                 and accepted only when it round-trips to the same string. Normalised to `Y-m-d`.
 *  - 'datetime' → strict `YYYY-MM-DD HH:MM:SS`, or `YYYY-MM-DD HH:MM` (same round-trip check).
 *                 Normalised to `Y-m-d H:i:s`.
 *  - 'enum:a,b,c' → exact, case-sensitive membership of the comma-separated list. Normalised to
 *                 the matched value.
 *
 * Required vs optional:
 *  - 'text' and 'html' are applied only when the field is present; they never reject.
 *  - 'int', 'date', 'datetime' and 'enum:…' are REQUIRED: an absent field returns
 *    HTTP 400 with the message `<field> is required`.
 *  - 'date', 'datetime' and 'enum:…' also have an OPTIONAL form written with a `?` suffix on the
 *    type name — 'date?', 'datetime?', 'enum?:a,b,c'. An optional field that is absent, null or
 *    an empty string is left exactly as it is (absent stays absent — it is NOT set to '') and the
 *    handler's own defaulting or required-field check still sees what the caller sent.
 *    ('int' has no optional form; its behaviour is unchanged.)
 *
 * Validation notes:
 *  - Dates and datetimes are naive wall-clock values, matching the storage convention documented in
 *    `.agents/skills/churchcrm/timezone-handling.md`. No timezone is attached, assumed or converted.
 *  - No trimming is done for 'date', 'datetime' and 'enum:…' — a value with surrounding whitespace
 *    is rejected, which is what the hand-rolled round-trip checks these types replace already did.
 *  - 'enum' values are split on `,` only, so an enum value cannot itself contain a comma. An empty
 *    list ('enum', 'enum:', 'enum:a,,b') throws an \InvalidArgumentException when the middleware is
 *    constructed, i.e. at route-registration time.
 *  - An unrecognised type string keeps the historical fallback and is treated as 'text'.
 *
 * All rejections are HTTP 400 with the canonical SlimUtils::renderErrorJSON() body,
 * `{"success": false, "message": "…"}`, and the message names the offending field. (Until #9821
 * this class emitted its own `{"error": "…"}` shape; #9737 is unifying every API error on the
 * canonical one, so this was the last holdout among the middlewares.)
 *
 * Usage:
 *   ->add(new InputSanitizationMiddleware([
 *       'title'    => 'text',
 *       'content'  => 'html',
 *       'level'    => 'int',
 *       'dueDate'  => 'date?',
 *       'startsAt' => 'datetime',
 *       'status'   => 'enum:open,closed',
 *   ]))
 */
class InputSanitizationMiddleware implements MiddlewareInterface
{
    private const KIND_TEXT = 'text';
    private const KIND_HTML = 'html';
    private const KIND_INT = 'int';
    private const KIND_DATE = 'date';
    private const KIND_DATETIME = 'datetime';
    private const KIND_ENUM = 'enum';

    private const DATE_FORMAT = '!Y-m-d';

    /**
     * Accepted datetime input formats, in the order they are tried. The first is also the
     * canonical output format.
     */
    private const DATETIME_FORMATS = ['!Y-m-d H:i:s', '!Y-m-d H:i'];

    /**
     * The parsed $fieldMap: field name → ['kind' => …, 'optional' => bool, 'values' => list<string>].
     *
     * @var array<string, array{kind: string, optional: bool, values: array<int, string>}>
     */
    private readonly array $fieldSpecs;

    /**
     * @param array<string, 'text'|'html'|'int'|'date'|'date?'|'datetime'|'datetime?'|string> $fieldMap
     *        Map of field name → sanitization type. Enum types are written 'enum:a,b,c'
     *        (or 'enum?:a,b,c' for the optional form).
     *
     * @throws \InvalidArgumentException when an 'enum' type declares an empty value list
     */
    public function __construct(array $fieldMap)
    {
        $specs = [];
        foreach ($fieldMap as $field => $type) {
            $specs[$field] = self::parseType((string) $field, (string) $type);
        }
        $this->fieldSpecs = $specs;
    }

    /**
     * @return array{kind: string, optional: bool, values: array<int, string>}
     *
     * @throws \InvalidArgumentException
     */
    private static function parseType(string $field, string $type): array
    {
        if (str_starts_with($type, self::KIND_ENUM . ':') || str_starts_with($type, self::KIND_ENUM . '?:')) {
            $optional = str_starts_with($type, self::KIND_ENUM . '?:');
            $list = substr($type, strpos($type, ':') + 1);
            // Split on ',' only — an enum value cannot itself contain a comma.
            $values = explode(',', $list);
            if ($list === '' || in_array('', $values, true)) {
                throw new \InvalidArgumentException("Invalid enum type for field '$field': the value list must not be empty");
            }

            return ['kind' => self::KIND_ENUM, 'optional' => $optional, 'values' => $values];
        }

        $optional = str_ends_with($type, '?');
        $name = $optional ? substr($type, 0, -1) : $type;

        if ($name === self::KIND_ENUM) {
            // 'enum' / 'enum?' with no ':' list — catch the typo instead of silently
            // falling through to the 'text' default.
            throw new \InvalidArgumentException("Invalid enum type for field '$field': the value list must not be empty");
        }

        $kind = match ($name) {
            self::KIND_DATE     => self::KIND_DATE,
            self::KIND_DATETIME => self::KIND_DATETIME,
            self::KIND_HTML     => self::KIND_HTML,
            self::KIND_INT      => self::KIND_INT,
            // Historical fallback: anything unrecognised is sanitized as text.
            default => self::KIND_TEXT,
        };

        // 'int', 'text' and 'html' have no optional form; their behaviour is unchanged.
        if ($kind !== self::KIND_DATE && $kind !== self::KIND_DATETIME) {
            $optional = false;
        }

        return ['kind' => $kind, 'optional' => $optional, 'values' => []];
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();

        if ($body === null) {
            $body = [];
        }

        if (is_array($body)) {
            foreach ($this->fieldSpecs as $field => $spec) {
                $kind = $spec['kind'];

                if ($kind === self::KIND_INT) {
                    // Integer fields are strictly required and validated:
                    // absent or non-integer values result in HTTP 400.
                    if (!array_key_exists($field, $body)) {
                        return self::reject("$field is required");
                    }
                    $validated = filter_var(trim((string) $body[$field]), FILTER_VALIDATE_INT);
                    if ($validated === false) {
                        return self::reject("Invalid integer value for $field");
                    }
                    $body[$field] = $validated;
                    continue;
                }

                if ($kind === self::KIND_DATE || $kind === self::KIND_DATETIME || $kind === self::KIND_ENUM) {
                    $value = $body[$field] ?? null;
                    if (!array_key_exists($field, $body) || $value === null || $value === '') {
                        // The optional form leaves an unsupplied value exactly as it is:
                        // an absent field stays absent, it is not set to ''.
                        if ($spec['optional']) {
                            continue;
                        }
                        if (!array_key_exists($field, $body)) {
                            return self::reject("$field is required");
                        }
                    }

                    $normalized = is_scalar($value) ? self::normalize($kind, (string) $value, $spec['values']) : null;
                    if ($normalized === null) {
                        return self::reject(self::invalidMessage($kind, $field, $spec['values']));
                    }
                    $body[$field] = $normalized;
                    continue;
                }

                if (isset($body[$field])) {
                    $body[$field] = match ($kind) {
                        self::KIND_HTML => InputUtils::sanitizeHTML($body[$field]),
                        default         => InputUtils::sanitizeText($body[$field]),
                    };
                }
            }
            $request = $request->withParsedBody($body);
        }

        return $handler->handle($request);
    }

    /**
     * Validates $value for $kind and returns the canonical form, or null when it is invalid.
     *
     * @param array<int, string> $allowed
     */
    private static function normalize(string $kind, string $value, array $allowed): ?string
    {
        if ($kind === self::KIND_ENUM) {
            return in_array($value, $allowed, true) ? $value : null;
        }

        if ($kind === self::KIND_DATE) {
            return self::roundTrip(self::DATE_FORMAT, $value)?->format('Y-m-d');
        }

        foreach (self::DATETIME_FORMATS as $format) {
            $parsed = self::roundTrip($format, $value);
            if ($parsed !== null) {
                // Canonical output is always 'Y-m-d H:i:s', including for the 'Y-m-d H:i' input form.
                return $parsed->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    /**
     * Parses $value strictly with $format — naive wall-clock, no timezone attached or converted —
     * and accepts it only when formatting it back yields the identical string. This is what rejects
     * garbage ("2026-02-30" rolls over to March, "tomorrow" becomes "now") that a lenient
     * \DateTime constructor would silently accept.
     *
     * @return \DateTimeImmutable|null the parsed value, or null when $value is not exactly $format
     */
    private static function roundTrip(string $format, string $value): ?\DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat($format, $value);
        if ($parsed === false) {
            return null;
        }
        // Strip the '!' reset marker: it is a parsing directive, not an output directive.
        $outputFormat = ltrim($format, '!');

        return $parsed->format($outputFormat) === $value ? $parsed : null;
    }

    /**
     * @param array<int, string> $allowed
     */
    private static function invalidMessage(string $kind, string $field, array $allowed): string
    {
        return match ($kind) {
            self::KIND_DATE     => "Invalid date value for $field - expected YYYY-MM-DD",
            self::KIND_DATETIME => "Invalid date/time value for $field - expected YYYY-MM-DD HH:MM:SS",
            default             => "Invalid value for $field - expected one of: " . implode(', ', $allowed),
        };
    }

    private static function reject(string $message): ResponseInterface
    {
        // The one API error contract (#9737): {"success": false, "message": "..."}.
        // Before #9821 this class emitted its own {"error": "..."} shape.
        return SlimUtils::renderErrorJSON(new Response(), $message, [], 400);
    }
}
