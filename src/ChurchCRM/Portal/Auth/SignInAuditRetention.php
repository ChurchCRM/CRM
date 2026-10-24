<?php

namespace ChurchCRM\Portal\Auth;

use ChurchCRM\model\ChurchCRM\PortalSigninAuditQuery;
use ChurchCRM\Service\RateLimiter;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeImmutable;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * How long the member sign-in audit keeps what was typed at the sign-in pages
 * (design §11.2). A row that names no person is deleted after 30 days. After
 * 90 days every row loses its IP address, and a refused or throttled row loses
 * the address that was typed. A person's own history stays with them.
 *
 * Run once a day from the timer jobs.
 */
final class SignInAuditRetention
{
    public const UNMATCHED_DAYS = 30;
    public const IDENTIFIER_DAYS = 90;

    private const DAILY_BUCKET = 'signin-audit.retention';
    private const DAILY_KEY = 'daily';
    private const DAY_SECONDS = 86400;

    /**
     * @param bool $force run even though today's run has happened
     *
     * @return array{deleted: int, ipCleared: int, emailCleared: int}|null null when today's run has happened
     */
    public static function run(bool $force = false): ?array
    {
        if (!(new RateLimiter(self::DAILY_BUCKET, 1, self::DAY_SECONDS))->attempt(self::DAILY_KEY) && !$force) {
            return null;
        }

        $now = new DateTimeImmutable('now', DateTimeUtils::getConfiguredTimezone());
        $unmatchedBefore = $now->modify('-' . self::UNMATCHED_DAYS . ' days')->format('Y-m-d H:i:s');
        $identifiersBefore = $now->modify('-' . self::IDENTIFIER_DAYS . ' days')->format('Y-m-d H:i:s');

        return [
            'deleted' => PortalSigninAuditQuery::create()
                ->filterByPersonId(null, Criteria::ISNULL)
                ->filterByTime($unmatchedBefore, Criteria::LESS_THAN)
                ->delete(),
            'ipCleared' => PortalSigninAuditQuery::create()
                ->filterByTime($identifiersBefore, Criteria::LESS_THAN)
                ->filterByIp(null, Criteria::ISNOTNULL)
                ->update(['Ip' => null]),
            'emailCleared' => PortalSigninAuditQuery::create()
                ->filterByTime($identifiersBefore, Criteria::LESS_THAN)
                ->filterByEvent([SignInAuditEvent::REFUSED->value, SignInAuditEvent::THROTTLED->value], Criteria::IN)
                ->filterByEmail(null, Criteria::ISNOTNULL)
                ->update(['Email' => null]),
        ];
    }
}
