<?php

namespace ChurchCRM\Service;

/**
 * Value object returned by ConfirmReportService::sendFamilyEmails().
 *
 * Carries a machine-readable status plus counts so callers can build
 * specific error messages instead of re-throwing a generic RuntimeException.
 */
class ConfirmReportEmailResult
{
    /** All targeted families were emailed successfully. */
    public const STATUS_SUCCESS = 'success';

    /** No families with an email address were found — nothing was sent. */
    public const STATUS_NO_RECIPIENTS = 'no_recipients';

    /** Every send attempt failed (SMTP/connection error or email disabled). */
    public const STATUS_SMTP_FAILURE = 'smtp_failure';

    /** Route guard: email is not configured in System Settings. */
    public const STATUS_EMAIL_DISABLED = 'email_disabled';

    /** At least one family was emailed but at least one failed. */
    public const STATUS_PARTIAL_FAILURE = 'partial_failure';

    /**
     * @param string   $status        One of the STATUS_* constants.
     * @param int      $sentCount     Number of families successfully emailed.
     * @param int      $failedCount   Number of families that failed.
     * @param string[] $failedFamilies Family names that failed (empty on success).
     */
    public function __construct(
        public readonly string $status,
        public readonly int $sentCount,
        public readonly int $failedCount,
        public readonly array $failedFamilies = [],
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    /**
     * Build a human-readable summary message for this result.
     *
     * Both the route handler (AJAX JSON response) and the view template delegate
     * to this single implementation so adding a new status code only requires
     * one change.
     */
    public function toMessage(): string
    {
        return self::messageForStatus($this->status, $this->sentCount, $this->failedCount);
    }

    /**
     * Build a human-readable summary from raw status/count values.
     *
     * Used by the view when reconstructing a message from query-string params
     * (which carry the same status codes).
     */
    public static function messageForStatus(string $status, int $sentCount, int $failedCount): string
    {
        switch ($status) {
            case self::STATUS_NO_RECIPIENTS:
                return gettext('No families with an email address were found. Nothing was sent.');
            case self::STATUS_SMTP_FAILURE:
                return gettext('All email sends failed. Please check your SMTP settings in System Settings.');
            case self::STATUS_EMAIL_DISABLED:
                return gettext('Email is not configured. Please configure SMTP settings in System Settings.');
            case self::STATUS_PARTIAL_FAILURE:
                $total = $sentCount + $failedCount;
                return sprintf(
                    gettext('Sent %1$d of %2$d — %3$d families could not be reached.'),
                    $sentCount,
                    $total,
                    $failedCount
                );
            case self::STATUS_SUCCESS:
                return sprintf(
                    ngettext('Email sent to %d family.', 'Emails sent to %d families.', $sentCount),
                    $sentCount
                );
            default:
                return gettext('An unexpected error occurred while sending emails. Please check your SMTP settings and server logs.');
        }
    }

    /**
     * Serialize to an associative array for JSON responses.
     *
     * @return array{status: string, sentCount: int, failedCount: int, failedFamilies: string[]}
     */
    public function toArray(): array
    {
        return [
            'status'        => $this->status,
            'sentCount'     => $this->sentCount,
            'failedCount'   => $this->failedCount,
            'failedFamilies' => $this->failedFamilies,
        ];
    }
}
