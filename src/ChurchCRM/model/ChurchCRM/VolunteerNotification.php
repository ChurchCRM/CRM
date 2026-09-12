<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerNotification as BaseVolunteerNotification;

/**
 * Skeleton subclass for representing a row from the 'volunteer_notification_vntf' table.
 *
 * Volunteer Management v2 (#9705). The notification outbox: idempotent enqueue, a due time and a retry-safe send log.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerNotification extends BaseVolunteerNotification
{
    // Each type maps to one email template.
    public const TYPE_ASSIGNMENT = 'assignment';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_DECLINE_ALERT = 'decline_alert';
    public const TYPE_GAP_ALERT = 'gap_alert';
    public const TYPE_SIGNUP_CONFIRM = 'signup_confirm';
    public const TYPE_SWAP_PROPOSED = 'swap_proposed';
    public const TYPE_SWAP_RESOLVED = 'swap_resolved';

    /**
     * Every legal value of vntf_Type.
     *
     * @return string[]
     */
    public static function allTypes(): array
    {
        return [
            self::TYPE_ASSIGNMENT,
            self::TYPE_REMINDER,
            self::TYPE_DECLINE_ALERT,
            self::TYPE_GAP_ALERT,
            self::TYPE_SIGNUP_CONFIRM,
            self::TYPE_SWAP_PROPOSED,
            self::TYPE_SWAP_RESOLVED,
        ];
    }

    // 'failed' means the drain gave up after the retry limit, never 'will retry';
    // 'skipped' means delivery was deliberately not attempted.
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    /**
     * Every legal value of vntf_Status.
     *
     * @return string[]
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_SENT,
            self::STATUS_FAILED,
            self::STATUS_SKIPPED,
        ];
    }

    // The delivery extension point; email is the only channel in the first release.
    public const CHANNEL_EMAIL = 'email';

    /**
     * Every legal value of vntf_Channel.
     *
     * @return string[]
     */
    public static function allChannels(): array
    {
        return [
            self::CHANNEL_EMAIL,
        ];
    }
}
