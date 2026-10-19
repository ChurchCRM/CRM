<?php

namespace ChurchCRM\Plugins\SignupSheets;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Utils\InputUtils;

/**
 * Business rules for signup sheets.
 *
 * Holds all validation and normalisation so the route files stay thin and the
 * authenticated and public surfaces cannot drift apart on what a valid claim is.
 */
final class SignupSheetService
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    public const SOURCE_INTERNAL = 'internal';
    public const SOURCE_PUBLIC = 'public';

    /**
     * Public audit event types.
     *
     * EVENT_CLAIM is written only after a claim is actually accepted, so the
     * signup limit counts signups. Everything a visitor sends that does not
     * become a signup is recorded under its own rejection type and counted
     * separately, by the looser attempt limit.
     */
    public const EVENT_CLAIM = 'public_claim';
    public const EVENT_REJECTED_HONEYPOT = 'public_rejected_honeypot';
    public const EVENT_REJECTED_INVALID = 'public_rejected_invalid';
    public const EVENT_RATE_LIMITED = 'public_rate_limited';
    public const EVENT_CANCEL = 'public_cancel';

    /**
     * What counts as a submission attempt.
     *
     * EVENT_RATE_LIMITED is deliberately absent: a refusal is a record of the
     * limiter working, not a new attempt. Counting it would let a visitor who
     * keeps retrying extend their own window indefinitely.
     *
     * @var string[]
     */
    private const ATTEMPT_EVENT_TYPES = [
        self::EVENT_CLAIM,
        self::EVENT_REJECTED_HONEYPOT,
        self::EVENT_REJECTED_INVALID,
    ];

    private const MAX_QUANTITY = 99;
    private const MAX_SLOTS_PER_SHEET = 500;

    public function __construct(
        private readonly SignupSheetRepository $repository = new SignupSheetRepository()
    ) {
    }

    public function getRepository(): SignupSheetRepository
    {
        return $this->repository;
    }

    // =========================================================================
    // Reads
    // =========================================================================

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listSheets(): array
    {
        return $this->repository->listSheets();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSheet(int $sheetId): ?array
    {
        return $this->repository->findSheet($sheetId);
    }

    /**
     * A sheet plus its slots grouped by category, each with remaining capacity.
     *
     * @param array<string, mixed> $sheet
     *
     * @return array<string, array<int, array<string, mixed>>> Category => slots
     */
    public function getSlotsByCategory(array $sheet): array
    {
        $slots = $this->repository->listSlots((int) $sheet['shs_ID']);
        $grouped = [];

        foreach ($slots as $slot) {
            $capacity = (int) $slot['sls_capacity'];
            $claimed = (int) $slot['claimed'];
            $slot['remaining'] = $capacity === 0 ? null : max(0, $capacity - $claimed);
            $slot['is_full'] = $capacity > 0 && $claimed >= $capacity;

            $category = trim((string) ($slot['sls_category'] ?? ''));
            $grouped[$category][] = $slot;
        }

        return $grouped;
    }

    /**
     * Claims on a sheet keyed by slot id, for rendering names under each slot.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function getClaimsBySlot(int $sheetId): array
    {
        $bySlot = [];
        foreach ($this->repository->listClaims($sheetId) as $claim) {
            $bySlot[(int) $claim['sgc_slot_id']][] = $claim;
        }

        return $bySlot;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listClaims(int $sheetId): array
    {
        return $this->repository->listClaims($sheetId);
    }

    // =========================================================================
    // Sheet lifecycle
    // =========================================================================

    /**
     * Normalise raw form input into a sheet payload.
     *
     * @param array<string, mixed> $body
     * @param string|null          $existingToken Token already issued for this sheet, if any
     *
     * @return array<string, mixed>
     *
     * @throws SignupValidationException
     */
    public function normalizeSheetInput(array $body, ?string $existingToken = null): array
    {
        $title = trim(InputUtils::sanitizeText($body['title'] ?? ''));
        if ($title === '') {
            throw new SignupValidationException(gettext('A sheet title is required.'));
        }

        $status = (string) ($body['status'] ?? self::STATUS_DRAFT);
        if (!in_array($status, [self::STATUS_DRAFT, self::STATUS_OPEN, self::STATUS_CLOSED], true)) {
            $status = self::STATUS_DRAFT;
        }

        $isPublic = $this->toBool($body['isPublic'] ?? false);
        $publicToken = $existingToken;
        if ($isPublic && empty($publicToken)) {
            $publicToken = self::generateToken();
        }

        return [
            'title' => mb_substr($title, 0, 255),
            'description' => $this->optionalText($body['description'] ?? null, 4000),
            'eventId' => $this->optionalInt($body['eventId'] ?? null),
            'location' => $this->optionalText($body['location'] ?? null, 255),
            'starts' => $this->optionalDateTime($body['starts'] ?? null),
            'ends' => $this->optionalDateTime($body['ends'] ?? null),
            'status' => $status,
            'closeAt' => $this->optionalDateTime($body['closeAt'] ?? null),
            'isPublic' => $isPublic,
            'publicToken' => $publicToken,
            'requireEmail' => $this->toBool($body['requireEmail'] ?? true),
            'allowComments' => $this->toBool($body['allowComments'] ?? true),
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws SignupValidationException
     */
    public function createSheet(array $body, ?int $userId): int
    {
        return $this->repository->createSheet($this->normalizeSheetInput($body), $userId);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws SignupValidationException
     */
    public function updateSheet(int $sheetId, array $body): void
    {
        $existing = $this->repository->findSheet($sheetId);
        if ($existing === null) {
            throw new SignupValidationException(gettext('That signup sheet no longer exists.'));
        }

        $this->repository->updateSheet(
            $sheetId,
            $this->normalizeSheetInput($body, $existing['shs_public_token'] ?: null)
        );
    }

    public function deleteSheet(int $sheetId): void
    {
        $this->repository->deleteSheet($sheetId);
    }

    // =========================================================================
    // Slot lifecycle
    // =========================================================================

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     *
     * @throws SignupValidationException
     */
    public function normalizeSlotInput(array $body, int $sortOrder): array
    {
        $title = trim(InputUtils::sanitizeText($body['title'] ?? ''));
        if ($title === '') {
            throw new SignupValidationException(gettext('Each slot needs a name — for example "Bring a dessert" or "Greeter".'));
        }

        $capacity = $this->optionalInt($body['capacity'] ?? null) ?? 1;
        if ($capacity < 0 || $capacity > 9999) {
            throw new SignupValidationException(gettext('Slot capacity must be between 0 and 9999. Use 0 for unlimited.'));
        }

        return [
            'category' => $this->optionalText($body['category'] ?? null, 100),
            'title' => mb_substr($title, 0, 255),
            'description' => $this->optionalText($body['description'] ?? null, 1000),
            'starts' => $this->optionalDateTime($body['starts'] ?? null),
            'ends' => $this->optionalDateTime($body['ends'] ?? null),
            'capacity' => $capacity,
            'allowQuantity' => $this->toBool($body['allowQuantity'] ?? false),
            'sortOrder' => $sortOrder,
        ];
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws SignupValidationException
     */
    public function createSlot(int $sheetId, array $body): int
    {
        if (count($this->repository->listSlots($sheetId)) >= self::MAX_SLOTS_PER_SHEET) {
            throw new SignupValidationException(gettext('This sheet already has the maximum number of slots.'));
        }

        $sortOrder = $this->optionalInt($body['sortOrder'] ?? null) ?? $this->repository->nextSortOrder($sheetId);

        return $this->repository->createSlot($sheetId, $this->normalizeSlotInput($body, $sortOrder));
    }

    /**
     * @param array<string, mixed> $body
     *
     * @throws SignupValidationException
     */
    public function updateSlot(int $slotId, array $body): void
    {
        $existing = $this->repository->findSlot($slotId);
        if ($existing === null) {
            throw new SignupValidationException(gettext('That slot no longer exists.'));
        }

        $sortOrder = $this->optionalInt($body['sortOrder'] ?? null) ?? (int) $existing['sls_sort_order'];
        $this->repository->updateSlot($slotId, $this->normalizeSlotInput($body, $sortOrder));
    }

    public function deleteSlot(int $slotId): void
    {
        $this->repository->deleteSlot($slotId);
    }

    // =========================================================================
    // Claiming
    // =========================================================================

    /**
     * Is this sheet currently accepting signups?
     *
     * @param array<string, mixed> $sheet
     */
    public function isAcceptingSignups(array $sheet): bool
    {
        if ((string) $sheet['shs_status'] !== self::STATUS_OPEN) {
            return false;
        }

        $closeAt = $sheet['shs_close_at'] ?? null;
        if (!empty($closeAt) && strtotime((string) $closeAt) < time()) {
            return false;
        }

        return true;
    }

    /**
     * Validate and record a claim on a slot.
     *
     * @param array<string, mixed> $sheet
     * @param array<string, mixed> $body
     *
     * @return array{claimId: int, manageToken: string}
     *
     * @throws SignupValidationException
     */
    public function claimSlot(array $sheet, int $slotId, array $body, string $source, ?int $userId): array
    {
        if (!$this->isAcceptingSignups($sheet)) {
            throw new SignupValidationException(gettext('This signup sheet is not open for signups.'));
        }

        $slot = $this->repository->findSlot($slotId);
        if ($slot === null || (int) $slot['sls_sheet_id'] !== (int) $sheet['shs_ID']) {
            throw new SignupValidationException(gettext('That slot is not part of this signup sheet.'));
        }

        $name = trim(InputUtils::sanitizeText($body['name'] ?? ''));
        if ($name === '') {
            throw new SignupValidationException(gettext('Please enter your name.'));
        }

        $email = trim((string) ($body['email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new SignupValidationException(gettext('That email address does not look valid.'));
        }
        if ($email === '' && !empty($sheet['shs_require_email'])) {
            throw new SignupValidationException(gettext('An email address is required for this sheet.'));
        }

        $quantity = $this->optionalInt($body['quantity'] ?? null) ?? 1;
        if (!$this->toBool($slot['sls_allow_quantity'])) {
            $quantity = 1;
        }
        if ($quantity < 1 || $quantity > self::MAX_QUANTITY) {
            throw new SignupValidationException(gettext('Please choose a quantity between 1 and 99.'));
        }

        $comment = empty($sheet['shs_allow_comments'])
            ? null
            : $this->optionalText($body['comment'] ?? null, 1000);

        $claimId = $this->repository->claimSlot($slotId, [
            'personId' => $this->optionalInt($body['personId'] ?? null),
            'name' => mb_substr($name, 0, 255),
            'email' => $email === '' ? null : mb_substr($email, 0, 254),
            'phone' => $this->optionalText($body['phone'] ?? null, 50),
            'quantity' => $quantity,
            'comment' => $comment,
            'source' => $source === self::SOURCE_PUBLIC ? self::SOURCE_PUBLIC : self::SOURCE_INTERNAL,
            'manageToken' => self::generateToken(),
            'createdBy' => $userId,
        ]);

        if ($claimId === null) {
            throw new SignupValidationException(gettext('Sorry — that slot filled up before your signup went through.'));
        }

        $claim = $this->repository->findClaim($claimId);

        return [
            'claimId' => $claimId,
            'manageToken' => (string) ($claim['sgc_manage_token'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findClaim(int $claimId): ?array
    {
        return $this->repository->findClaim($claimId);
    }

    public function deleteClaim(int $claimId): void
    {
        $this->repository->deleteClaim($claimId);
    }

    // =========================================================================
    // Public surface helpers
    // =========================================================================

    /**
     * @return array<string, mixed>|null
     */
    public function findPublicSheet(string $token): ?array
    {
        if (!self::isTokenShaped($token)) {
            return null;
        }

        return $this->repository->findSheetByToken($token);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findClaimByManageToken(string $token): ?array
    {
        if (!self::isTokenShaped($token)) {
            return null;
        }

        return $this->repository->findClaimByToken($token);
    }

    public function hashIp(?string $ipAddress): string
    {
        return hash('sha256', (string) $ipAddress);
    }

    /**
     * Has this visitor used up their hourly signup allowance?
     *
     * Counts accepted signups only. This is the business rule — how many slots
     * one household may take in an hour — not the abuse control.
     */
    public function isClaimLimitReached(string $ipHash, int $limit): bool
    {
        return $this->repository->countRecentEvents($ipHash, [self::EVENT_CLAIM]) >= max(1, $limit);
    }

    /**
     * Has this visitor sent too many submissions of any kind in the last hour?
     *
     * Counts accepted and rejected submissions alike, so malformed traffic is
     * throttled even though it never produces a signup. Kept deliberately
     * looser than the signup limit: several people can share one NAT address,
     * and a few fumbled forms must not consume the whole household allowance.
     */
    public function isAttemptLimitReached(string $ipHash, int $limit): bool
    {
        return $this->repository->countRecentEvents($ipHash, self::ATTEMPT_EVENT_TYPES) >= max(1, $limit);
    }

    public function audit(string $eventType, ?int $sheetId, ?string $ipHash): void
    {
        $this->repository->audit($eventType, $sheetId, $ipHash);
    }

    public function publicUrl(string $token): string
    {
        return SystemURLs::getURL() . '/external/signup-sheets/' . $token;
    }

    // =========================================================================
    // Export
    // =========================================================================

    /**
     * Roster as CSV rows, header first.
     *
     * @return array<int, array<int, string>>
     */
    public function buildRosterCsv(int $sheetId): array
    {
        $rows = [[
            gettext('Category'),
            gettext('Slot'),
            gettext('Name'),
            gettext('Email'),
            gettext('Phone'),
            gettext('Quantity'),
            gettext('Comment'),
            gettext('Source'),
            gettext('Signed Up'),
        ]];

        foreach ($this->repository->listClaims($sheetId) as $claim) {
            $rows[] = [
                (string) ($claim['slot_category'] ?? ''),
                (string) $claim['slot_title'],
                (string) $claim['sgc_name'],
                (string) ($claim['sgc_email'] ?? ''),
                (string) ($claim['sgc_phone'] ?? ''),
                (string) $claim['sgc_quantity'],
                (string) ($claim['sgc_comment'] ?? ''),
                (string) $claim['sgc_source'],
                (string) $claim['sgc_created_at'],
            ];
        }

        return $rows;
    }

    // =========================================================================
    // Primitives
    // =========================================================================

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function isTokenShaped(string $token): bool
    {
        return preg_match('/^[0-9a-f]{32}$/', $token) === 1;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes'], true);
    }

    private function optionalInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function optionalText(mixed $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $clean = trim(InputUtils::sanitizeText((string) $value));

        return $clean === '' ? null : mb_substr($clean, 0, $maxLength);
    }

    /**
     * Accept a datetime-local value and store it as MySQL DATETIME.
     */
    private function optionalDateTime(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);

        return $timestamp === false ? null : date('Y-m-d H:i:s', $timestamp);
    }
}
