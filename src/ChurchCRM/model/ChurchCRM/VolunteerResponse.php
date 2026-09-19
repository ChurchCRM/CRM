<?php

namespace ChurchCRM\model\ChurchCRM;

use ChurchCRM\model\ChurchCRM\Base\VolunteerResponse as BaseVolunteerResponse;

/**
 * Skeleton subclass for representing a row from the 'volunteer_response_vrsp' table.
 *
 * Volunteer Management v2 (#9705). Append-only history of every response to an assignment. Rows are never updated or deleted.
 *
 * Deliberately holds no business logic and no pre* authorization hooks: the
 * Group and Person2group2roleP2g2r models gate saves through AuthService, which
 * reads $_SESSION flags an API-key caller never sets, so a non-admin coordinator
 * could not save through the ORM at all. V2 authorizes in middleware and
 * services instead (#9706).
 */
class VolunteerResponse extends BaseVolunteerResponse
{
    // One value per swap outcome, so every swap state transition leaves a row.
    public const RESPONSE_ACCEPTED = 'accepted';
    public const RESPONSE_DECLINED = 'declined';
    public const RESPONSE_CANCELLED = 'cancelled';
    public const RESPONSE_SUBSTITUTE_PROPOSED = 'substitute_proposed';
    public const RESPONSE_SUBSTITUTE_APPROVED = 'substitute_approved';
    public const RESPONSE_SUBSTITUTE_REJECTED = 'substitute_rejected';
    public const RESPONSE_SUBSTITUTE_WITHDRAWN = 'substitute_withdrawn';

    /**
     * Every legal value of vrsp_Response.
     *
     * @return string[]
     */
    public static function allResponses(): array
    {
        return [
            self::RESPONSE_ACCEPTED,
            self::RESPONSE_DECLINED,
            self::RESPONSE_CANCELLED,
            self::RESPONSE_SUBSTITUTE_PROPOSED,
            self::RESPONSE_SUBSTITUTE_APPROVED,
            self::RESPONSE_SUBSTITUTE_REJECTED,
            self::RESPONSE_SUBSTITUTE_WITHDRAWN,
        ];
    }

    // 'email_token' is reserved for the future tokenized-link extension and is
    // deliberately not part of the enum yet.
    public const CHANNEL_WEB = 'web';
    public const CHANNEL_COORDINATOR = 'coordinator';

    /**
     * Every legal value of vrsp_Channel.
     *
     * @return string[]
     */
    public static function allChannels(): array
    {
        return [
            self::CHANNEL_WEB,
            self::CHANNEL_COORDINATOR,
        ];
    }
}
