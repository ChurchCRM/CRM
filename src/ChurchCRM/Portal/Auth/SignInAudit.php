<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\PortalSigninAudit;
use ChurchCRM\model\ChurchCRM\PortalSigninAuditQuery;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use DateTimeImmutable;
use Throwable;

/**
 * Writes the member sign-in audit (design §11.2, §14).
 *
 * The audit must never hold a code, link, token, password or passkey secret,
 * so there is no field for one: callers pass an event, a method, a reason and,
 * where the design asks for it, the address that was typed or used. `$detail` is
 * for non-secret context only, such as the previous address on an email change.
 *
 * A failed write is logged and swallowed: losing an audit row must not stop a
 * session from ending or a refusal from being answered.
 */
final class SignInAudit
{
    private const TEXT_LIMIT = 255;
    private const IP_LIMIT = 45;

    /**
     * @return int|null the new row's id, or null when it could not be written
     */
    public static function record(
        SignInAuditEvent $event,
        ?int $personId,
        ?SignInMethod $method = null,
        ?SignInReason $reason = null,
        ?int $actorUserId = null,
        ?string $email = null,
        ?string $detail = null,
    ): ?int {
        // During Login as User every row names the administrator too (§6.5, #10511 B1).
        $actorUserId ??= PortalSessionStore::load()?->actorUserId;

        try {
            $row = (new PortalSigninAudit())
                ->setTime(new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone()))
                ->setPersonId($personId)
                ->setEvent($event->value)
                ->setMethod($method?->value)
                ->setReason($reason?->value)
                ->setActorUserId($actorUserId)
                ->setEmail(self::clip($email === null ? null : trim($email), self::TEXT_LIMIT))
                ->setIp(self::clip($_SERVER['REMOTE_ADDR'] ?? null, self::IP_LIMIT))
                ->setDetail(self::clip($detail, self::TEXT_LIMIT));
            $row->save();

            return (int) $row->getId();
        } catch (Throwable $e) {
            LoggerUtils::getAuthLogger()->error('Could not write the member sign-in audit', [
                'event' => $event->value,
                'personId' => $personId,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Sets the detail of a row written earlier, for an outcome known only
     * after the response, such as an email that could not be sent.
     */
    public static function setDetail(int $auditId, string $detail): void
    {
        try {
            PortalSigninAuditQuery::create()
                ->filterById($auditId)
                ->update(['Detail' => self::clip($detail, self::TEXT_LIMIT)]);
        } catch (Throwable $e) {
            LoggerUtils::getAuthLogger()->error('Could not update the member sign-in audit', [
                'auditId' => $auditId,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private static function clip(?string $value, int $limit): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}
