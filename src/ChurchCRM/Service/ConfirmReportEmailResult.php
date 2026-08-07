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
