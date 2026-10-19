<?php

namespace ChurchCRM\Plugins\SignupSheets;

use PDO;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

/**
 * Data access for the Signup Sheets plugin.
 *
 * Every statement is prepared and parameterised. The plugin owns exactly four
 * tables — signupsheet_shs, signupslot_sls, signupclaim_sgc, signupaudit_sga —
 * plus read-only joins to person_per and events_event for display.
 */
final class SignupSheetRepository
{
    /**
     * The connection is resolved on first use rather than in the constructor:
     * plugin routes are registered on every /plugins and /external request, and
     * most of those requests never touch a signup sheet.
     */
    public function __construct(private ?ConnectionInterface $connection = null)
    {
    }

    private function connection(): ConnectionInterface
    {
        return $this->connection ??= Propel::getWriteConnection('default');
    }

    // =========================================================================
    // Sheets
    // =========================================================================

    /**
     * All sheets, newest first, with slot and claim rollups for the list page.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSheets(): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT s.*,
       e.event_title AS event_title,
       (SELECT COUNT(*) FROM signupslot_sls WHERE sls_sheet_id = s.shs_ID) AS slot_count,
       (SELECT COALESCE(SUM(sls_capacity), 0) FROM signupslot_sls WHERE sls_sheet_id = s.shs_ID) AS capacity_total,
       (SELECT COALESCE(SUM(c.sgc_quantity), 0)
          FROM signupclaim_sgc c
          JOIN signupslot_sls sl ON sl.sls_ID = c.sgc_slot_id
         WHERE sl.sls_sheet_id = s.shs_ID) AS claimed_total
  FROM signupsheet_shs s
  LEFT JOIN events_event e ON e.event_id = s.shs_event_id
 ORDER BY COALESCE(s.shs_starts, s.shs_created_at) DESC, s.shs_ID DESC
SQL);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSheet(int $sheetId): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT s.*, e.event_title AS event_title
  FROM signupsheet_shs s
  LEFT JOIN events_event e ON e.event_id = s.shs_event_id
 WHERE s.shs_ID = :id
SQL);
        $statement->execute(['id' => $sheetId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Look up a sheet by its public share token.
     *
     * @return array<string, mixed>|null
     */
    public function findSheetByToken(string $token): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT s.*, e.event_title AS event_title
  FROM signupsheet_shs s
  LEFT JOIN events_event e ON e.event_id = s.shs_event_id
 WHERE s.shs_public_token = :token
   AND s.shs_is_public = 1
SQL);
        $statement->execute(['token' => $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createSheet(array $data, ?int $userId): int
    {
        $statement = $this->connection()->prepare(<<<'SQL'
INSERT INTO signupsheet_shs
  (shs_title, shs_description, shs_event_id, shs_location, shs_starts, shs_ends,
   shs_status, shs_close_at, shs_is_public, shs_public_token, shs_require_email,
   shs_allow_comments, shs_created_by)
VALUES
  (:title, :description, :event_id, :location, :starts, :ends,
   :status, :close_at, :is_public, :public_token, :require_email,
   :allow_comments, :created_by)
SQL);
        $statement->execute([
            'title' => $data['title'],
            'description' => $data['description'],
            'event_id' => $data['eventId'],
            'location' => $data['location'],
            'starts' => $data['starts'],
            'ends' => $data['ends'],
            'status' => $data['status'],
            'close_at' => $data['closeAt'],
            'is_public' => $data['isPublic'] ? 1 : 0,
            'public_token' => $data['publicToken'],
            'require_email' => $data['requireEmail'] ? 1 : 0,
            'allow_comments' => $data['allowComments'] ? 1 : 0,
            'created_by' => $userId,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateSheet(int $sheetId, array $data): void
    {
        $statement = $this->connection()->prepare(<<<'SQL'
UPDATE signupsheet_shs
   SET shs_title = :title,
       shs_description = :description,
       shs_event_id = :event_id,
       shs_location = :location,
       shs_starts = :starts,
       shs_ends = :ends,
       shs_status = :status,
       shs_close_at = :close_at,
       shs_is_public = :is_public,
       shs_public_token = :public_token,
       shs_require_email = :require_email,
       shs_allow_comments = :allow_comments
 WHERE shs_ID = :id
SQL);
        $statement->execute([
            'id' => $sheetId,
            'title' => $data['title'],
            'description' => $data['description'],
            'event_id' => $data['eventId'],
            'location' => $data['location'],
            'starts' => $data['starts'],
            'ends' => $data['ends'],
            'status' => $data['status'],
            'close_at' => $data['closeAt'],
            'is_public' => $data['isPublic'] ? 1 : 0,
            'public_token' => $data['publicToken'],
            'require_email' => $data['requireEmail'] ? 1 : 0,
            'allow_comments' => $data['allowComments'] ? 1 : 0,
        ]);
    }

    public function deleteSheet(int $sheetId): void
    {
        $statement = $this->connection()->prepare('DELETE FROM signupsheet_shs WHERE shs_ID = :id');
        $statement->execute(['id' => $sheetId]);
    }

    // =========================================================================
    // Slots
    // =========================================================================

    /**
     * Slots for a sheet with the quantity already claimed on each.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listSlots(int $sheetId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT sl.*,
       (SELECT COALESCE(SUM(sgc_quantity), 0)
          FROM signupclaim_sgc WHERE sgc_slot_id = sl.sls_ID) AS claimed
  FROM signupslot_sls sl
 WHERE sl.sls_sheet_id = :sheet_id
 ORDER BY sl.sls_sort_order ASC, sl.sls_ID ASC
SQL);
        $statement->execute(['sheet_id' => $sheetId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSlot(int $slotId): ?array
    {
        $statement = $this->connection()->prepare('SELECT * FROM signupslot_sls WHERE sls_ID = :id');
        $statement->execute(['id' => $slotId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createSlot(int $sheetId, array $data): int
    {
        $statement = $this->connection()->prepare(<<<'SQL'
INSERT INTO signupslot_sls
  (sls_sheet_id, sls_category, sls_title, sls_description, sls_starts, sls_ends,
   sls_capacity, sls_allow_quantity, sls_sort_order)
VALUES
  (:sheet_id, :category, :title, :description, :starts, :ends,
   :capacity, :allow_quantity, :sort_order)
SQL);
        $statement->execute([
            'sheet_id' => $sheetId,
            'category' => $data['category'],
            'title' => $data['title'],
            'description' => $data['description'],
            'starts' => $data['starts'],
            'ends' => $data['ends'],
            'capacity' => $data['capacity'],
            'allow_quantity' => $data['allowQuantity'] ? 1 : 0,
            'sort_order' => $data['sortOrder'],
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateSlot(int $slotId, array $data): void
    {
        $statement = $this->connection()->prepare(<<<'SQL'
UPDATE signupslot_sls
   SET sls_category = :category,
       sls_title = :title,
       sls_description = :description,
       sls_starts = :starts,
       sls_ends = :ends,
       sls_capacity = :capacity,
       sls_allow_quantity = :allow_quantity,
       sls_sort_order = :sort_order
 WHERE sls_ID = :id
SQL);
        $statement->execute([
            'id' => $slotId,
            'category' => $data['category'],
            'title' => $data['title'],
            'description' => $data['description'],
            'starts' => $data['starts'],
            'ends' => $data['ends'],
            'capacity' => $data['capacity'],
            'allow_quantity' => $data['allowQuantity'] ? 1 : 0,
            'sort_order' => $data['sortOrder'],
        ]);
    }

    public function deleteSlot(int $slotId): void
    {
        $statement = $this->connection()->prepare('DELETE FROM signupslot_sls WHERE sls_ID = :id');
        $statement->execute(['id' => $slotId]);
    }

    public function nextSortOrder(int $sheetId): int
    {
        $statement = $this->connection()->prepare(
            'SELECT COALESCE(MAX(sls_sort_order), 0) + 1 FROM signupslot_sls WHERE sls_sheet_id = :sheet_id'
        );
        $statement->execute(['sheet_id' => $sheetId]);

        return (int) $statement->fetchColumn();
    }

    // =========================================================================
    // Claims
    // =========================================================================

    /**
     * Every claim on a sheet, ordered for the roster view.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listClaims(int $sheetId): array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT c.*, sl.sls_title AS slot_title, sl.sls_category AS slot_category,
       sl.sls_sort_order AS slot_sort_order,
       p.per_FirstName AS person_first_name, p.per_LastName AS person_last_name
  FROM signupclaim_sgc c
  JOIN signupslot_sls sl ON sl.sls_ID = c.sgc_slot_id
  LEFT JOIN person_per p ON p.per_ID = c.sgc_person_id
 WHERE sl.sls_sheet_id = :sheet_id
 ORDER BY sl.sls_sort_order ASC, sl.sls_ID ASC, c.sgc_created_at ASC
SQL);
        $statement->execute(['sheet_id' => $sheetId]);

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findClaim(int $claimId): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT c.*, sl.sls_sheet_id AS sheet_id, sl.sls_title AS slot_title
  FROM signupclaim_sgc c
  JOIN signupslot_sls sl ON sl.sls_ID = c.sgc_slot_id
 WHERE c.sgc_ID = :id
SQL);
        $statement->execute(['id' => $claimId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Look up a claim by the token in a volunteer's manage link.
     *
     * @return array<string, mixed>|null
     */
    public function findClaimByToken(string $token): ?array
    {
        $statement = $this->connection()->prepare(<<<'SQL'
SELECT c.*, sl.sls_sheet_id AS sheet_id, sl.sls_title AS slot_title,
       s.shs_title AS sheet_title, s.shs_public_token AS sheet_token,
       s.shs_status AS sheet_status
  FROM signupclaim_sgc c
  JOIN signupslot_sls sl ON sl.sls_ID = c.sgc_slot_id
  JOIN signupsheet_shs s ON s.shs_ID = sl.sls_sheet_id
 WHERE c.sgc_manage_token = :token
SQL);
        $statement->execute(['token' => $token]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * Claim capacity on a slot, rejecting the claim if it would oversubscribe.
     *
     * The slot row is locked FOR UPDATE so two people clicking at the same
     * moment cannot both take the last spot.
     *
     * @param array<string, mixed> $data
     *
     * @return int|null New claim id, or null when the slot is already full
     */
    public function claimSlot(int $slotId, array $data): ?int
    {
        $this->connection()->beginTransaction();

        try {
            $lock = $this->connection()->prepare(
                'SELECT sls_capacity FROM signupslot_sls WHERE sls_ID = :id FOR UPDATE'
            );
            $lock->execute(['id' => $slotId]);
            $capacityColumn = $lock->fetchColumn();

            if ($capacityColumn === false) {
                $this->connection()->rollBack();

                return null;
            }

            $capacity = (int) $capacityColumn;

            // Capacity 0 means unlimited.
            if ($capacity > 0) {
                $taken = $this->connection()->prepare(
                    'SELECT COALESCE(SUM(sgc_quantity), 0) FROM signupclaim_sgc WHERE sgc_slot_id = :id'
                );
                $taken->execute(['id' => $slotId]);

                if ((int) $taken->fetchColumn() + (int) $data['quantity'] > $capacity) {
                    $this->connection()->rollBack();

                    return null;
                }
            }

            $insert = $this->connection()->prepare(<<<'SQL'
INSERT INTO signupclaim_sgc
  (sgc_slot_id, sgc_person_id, sgc_name, sgc_email, sgc_phone, sgc_quantity,
   sgc_comment, sgc_source, sgc_manage_token, sgc_created_by)
VALUES
  (:slot_id, :person_id, :name, :email, :phone, :quantity,
   :comment, :source, :manage_token, :created_by)
SQL);
            $insert->execute([
                'slot_id' => $slotId,
                'person_id' => $data['personId'],
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'quantity' => $data['quantity'],
                'comment' => $data['comment'],
                'source' => $data['source'],
                'manage_token' => $data['manageToken'],
                'created_by' => $data['createdBy'],
            ]);

            $claimId = (int) $this->connection()->lastInsertId();
            $this->connection()->commit();

            return $claimId;
        } catch (\Throwable $exception) {
            if ($this->connection()->inTransaction()) {
                $this->connection()->rollBack();
            }

            throw $exception;
        }
    }

    public function deleteClaim(int $claimId): void
    {
        $statement = $this->connection()->prepare('DELETE FROM signupclaim_sgc WHERE sgc_ID = :id');
        $statement->execute(['id' => $claimId]);
    }

    // =========================================================================
    // Audit / rate limiting
    // =========================================================================

    /**
     * Record a public-surface event.
     *
     * `sga_created_at` is written explicitly as UTC, and the column carries no
     * default so that forgetting to is an error rather than a silent bug. The
     * rate-limit window below compares against UTC_TIMESTAMP(); a timestamp
     * written in the MySQL session timezone would disagree with it by the
     * offset, and the window would either never close or never open.
     */
    public function audit(string $eventType, ?int $sheetId, ?string $ipHash): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO signupaudit_sga (sga_sheet_id, sga_event_type, sga_ip_hash, sga_created_at)
             VALUES (:sheet_id, :event_type, :ip_hash, UTC_TIMESTAMP())'
        );
        $statement->execute([
            'sheet_id' => $sheetId,
            'event_type' => $eventType,
            'ip_hash' => $ipHash,
        ]);
    }

    /**
     * How many of the given events this visitor has recorded in the last hour.
     *
     * @param string[] $eventTypes
     */
    public function countRecentEvents(string $ipHash, array $eventTypes): int
    {
        if ($eventTypes === []) {
            return 0;
        }

        $placeholders = implode(', ', array_fill(0, count($eventTypes), '?'));
        $statement = $this->connection()->prepare(
            'SELECT COUNT(*) FROM signupaudit_sga
              WHERE sga_ip_hash = ?
                AND sga_event_type IN (' . $placeholders . ')
                AND sga_created_at >= (UTC_TIMESTAMP() - INTERVAL 1 HOUR)'
        );
        $statement->execute(array_merge([$ipHash], array_values($eventTypes)));

        return (int) $statement->fetchColumn();
    }
}
