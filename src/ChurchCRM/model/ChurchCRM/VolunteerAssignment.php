<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerAssignment as BaseVolunteerAssignment;

/**
 * Skeleton subclass for representing a row from the 'volunteer_assignment_vasg' table.
 *
 * Volunteer Management v2 (#9705). A person serving a position on an occurrence, with an explicit lifecycle.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerAssignment extends BaseVolunteerAssignment
{
    // Lifecycle: pending -> accepted|declined|cancelled;
    // accepted -> substituted|completed|declined. The terminal states are
    // declined, cancelled, substituted and completed. Only VolunteerAssignmentService
    // (#9709) may move a row between them.
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SUBSTITUTED = 'substituted';
    public const STATUS_COMPLETED = 'completed';

    /**
     * Every legal value of vasg_Status.
     *
     * @return string[]
     */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
            self::STATUS_SUBSTITUTED,
            self::STATUS_COMPLETED,
        ];
    }

    // Only pending and accepted rows count as live when a gap is derived.
    public const SOURCE_COORDINATOR = 'coordinator';
    public const SOURCE_SELF_SIGNUP = 'self_signup';
    public const SOURCE_SUBSTITUTE = 'substitute';

    /**
     * Every legal value of vasg_Source.
     *
     * @return string[]
     */
    public static function allSources(): array
    {
        return [
            self::SOURCE_COORDINATOR,
            self::SOURCE_SELF_SIGNUP,
            self::SOURCE_SUBSTITUTE,
        ];
    }
}
