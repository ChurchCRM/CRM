<?php

namespace ChurchCRM\Shepherd;

use PDO;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

final class SignupRequestRepository
{
    public function __construct(private ?ConnectionInterface $connection = null)
    {
        $this->connection ??= Propel::getWriteConnection('default');
    }

    public function ensureSchema(): void
    {
        $this->connection->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `shepherd_signup_request` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(254) NOT NULL,
  `username` varchar(50) NOT NULL,
  `note` text NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending_verification',
  `verification_token_hash` char(64) NULL,
  `verification_expires_at` datetime NULL,
  `verified_at` datetime NULL,
  `password_setup_token_hash` char(64) NULL,
  `password_setup_expires_at` datetime NULL,
  `password_setup_used_at` datetime NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `reviewed_at` datetime NULL,
  `reviewer_user_id` mediumint unsigned NULL,
  `linked_person_id` mediumint unsigned NULL,
  `rejection_reason` varchar(500) NULL,
  `request_ip_hash` char(64) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shepherd_signup_email_unique` (`email`),
  UNIQUE KEY `shepherd_signup_username_unique` (`username`),
  UNIQUE KEY `shepherd_signup_verify_token_unique` (`verification_token_hash`),
  UNIQUE KEY `shepherd_signup_password_token_unique` (`password_setup_token_hash`),
  KEY `shepherd_signup_status_idx` (`status`),
  KEY `shepherd_signup_created_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

        $this->connection->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS `shepherd_signup_audit` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint unsigned NULL,
  `event_type` varchar(64) NOT NULL,
  `actor_user_id` mediumint unsigned NULL,
  `ip_hash` char(64) NULL,
  `metadata_json` text NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `shepherd_audit_request_idx` (`request_id`),
  KEY `shepherd_audit_rate_idx` (`ip_hash`, `event_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }

    public function isRateLimited(string $ipHash, int $limit = 5): bool
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*) FROM shepherd_signup_audit
             WHERE ip_hash = :ip_hash AND event_type = 'signup_submitted'
               AND created_at >= (UTC_TIMESTAMP() - INTERVAL 1 HOUR)"
        );
        $statement->execute(['ip_hash' => $ipHash]);
        return (int) $statement->fetchColumn() >= $limit;
    }

    public function create(array $input, string $tokenHash, string $ipHash): ?int
    {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO shepherd_signup_request
  (first_name, last_name, email, username, note, verification_token_hash,
   verification_expires_at, request_ip_hash)
VALUES
  (:first_name, :last_name, :email, :username, :note, :token_hash,
   UTC_TIMESTAMP() + INTERVAL 24 HOUR, :ip_hash)
SQL);

        try {
            $statement->execute([
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'email' => $input['email'],
                'username' => $input['username'],
                'note' => $input['note'] ?: null,
                'token_hash' => $tokenHash,
                'ip_hash' => $ipHash,
            ]);
        } catch (\Throwable $exception) {
            if ((string) $exception->getCode() === '23000') {
                return null;
            }
            throw $exception;
        }

        return (int) $this->connection->lastInsertId();
    }

    public function findByVerificationToken(string $tokenHash): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM shepherd_signup_request
             WHERE verification_token_hash = :token_hash
               AND status = 'pending_verification'
               AND verification_expires_at > UTC_TIMESTAMP()",
            ['token_hash' => $tokenHash]
        );
    }

    public function verify(int $id): bool
    {
        $statement = $this->connection->prepare(<<<'SQL'
UPDATE shepherd_signup_request
SET status = 'pending_review', verified_at = UTC_TIMESTAMP(),
    verification_token_hash = NULL, verification_expires_at = NULL
WHERE id = :id AND status = 'pending_verification'
SQL);
        $statement->execute(['id' => $id]);
        return $statement->rowCount() === 1;
    }

    public function find(int $id): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM shepherd_signup_request WHERE id = :id',
            ['id' => $id]
        );
    }

    public function listForReview(): array
    {
        $statement = $this->connection->prepare(<<<'SQL'
SELECT * FROM shepherd_signup_request
ORDER BY FIELD(status, 'pending_review', 'pending_verification', 'approved', 'rejected'), created_at DESC
LIMIT 250
SQL);
        $statement->execute();
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function claimForApproval(int $id, int $reviewerId): bool
    {
        $statement = $this->connection->prepare(<<<'SQL'
UPDATE shepherd_signup_request
SET status = 'approving', reviewer_user_id = :reviewer_id, reviewed_at = UTC_TIMESTAMP()
WHERE id = :id AND status = 'pending_review'
SQL);
        $statement->execute(['id' => $id, 'reviewer_id' => $reviewerId]);
        return $statement->rowCount() === 1;
    }

    public function releaseApprovalClaim(int $id): void
    {
        $statement = $this->connection->prepare(<<<'SQL'
UPDATE shepherd_signup_request
SET status = 'pending_review', reviewer_user_id = NULL, reviewed_at = NULL
WHERE id = :id AND status = 'approving'
SQL);
        $statement->execute(['id' => $id]);
    }

    public function approve(int $id, int $reviewerId, int $personId, string $passwordTokenHash): bool
    {
        $statement = $this->connection->prepare(<<<'SQL'
UPDATE shepherd_signup_request
SET status = 'approved', reviewer_user_id = :reviewer_id,
    linked_person_id = :person_id, reviewed_at = UTC_TIMESTAMP(),
    password_setup_token_hash = :password_token_hash,
    password_setup_expires_at = UTC_TIMESTAMP() + INTERVAL 48 HOUR
WHERE id = :id AND status = 'approving'
SQL);
        $statement->execute([
            'id' => $id,
            'reviewer_id' => $reviewerId,
            'person_id' => $personId,
            'password_token_hash' => $passwordTokenHash,
        ]);
        return $statement->rowCount() === 1;
    }

    public function reject(int $id, int $reviewerId, string $reason): bool
    {
        $statement = $this->connection->prepare(<<<'SQL'
UPDATE shepherd_signup_request
SET status = 'rejected', reviewer_user_id = :reviewer_id,
    rejection_reason = :reason, reviewed_at = UTC_TIMESTAMP(),
    verification_token_hash = NULL, verification_expires_at = NULL
WHERE id = :id AND status IN ('pending_review', 'pending_verification')
SQL);
        $statement->execute(['id' => $id, 'reviewer_id' => $reviewerId, 'reason' => $reason]);
        return $statement->rowCount() === 1;
    }

    public function findByPasswordToken(string $tokenHash): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM shepherd_signup_request
             WHERE password_setup_token_hash = :token_hash
               AND status = 'approved'
               AND password_setup_used_at IS NULL
               AND password_setup_expires_at > UTC_TIMESTAMP()",
            ['token_hash' => $tokenHash]
        );
    }

    public function renewPasswordToken(int $id, string $tokenHash): bool
    {
        $statement = $this->connection->prepare(<<<'SQL'
UPDATE shepherd_signup_request
SET password_setup_token_hash = :token_hash,
    password_setup_expires_at = UTC_TIMESTAMP() + INTERVAL 48 HOUR,
    password_setup_used_at = NULL
WHERE id = :id AND status = 'approved'
SQL);
        $statement->execute(['id' => $id, 'token_hash' => $tokenHash]);
        return $statement->rowCount() === 1;
    }

    public function consumePasswordToken(int $id): bool
    {
        $statement = $this->connection->prepare(<<<'SQL'
UPDATE shepherd_signup_request
SET password_setup_used_at = UTC_TIMESTAMP(), password_setup_token_hash = NULL,
    password_setup_expires_at = NULL
WHERE id = :id AND status = 'approved' AND password_setup_used_at IS NULL
SQL);
        $statement->execute(['id' => $id]);
        return $statement->rowCount() === 1;
    }

    public function audit(
        string $eventType,
        ?int $requestId = null,
        ?int $actorUserId = null,
        ?string $ipHash = null,
        array $metadata = []
    ): void {
        $statement = $this->connection->prepare(<<<'SQL'
INSERT INTO shepherd_signup_audit
  (request_id, event_type, actor_user_id, ip_hash, metadata_json)
VALUES (:request_id, :event_type, :actor_user_id, :ip_hash, :metadata_json)
SQL);
        $statement->execute([
            'request_id' => $requestId,
            'event_type' => $eventType,
            'actor_user_id' => $actorUserId,
            'ip_hash' => $ipHash,
            'metadata_json' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }

    private function fetchOne(string $sql, array $parameters): ?array
    {
        $statement = $this->connection->prepare($sql);
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
