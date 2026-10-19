<?php

namespace ChurchCRM\Shepherd;

use PDO;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;

final class WebsiteContentRepository
{
    public function __construct(private ?ConnectionInterface $connection = null)
    {
        $this->connection ??= Propel::getWriteConnection('default');
    }

    public function find(string $pageKey): ?array
    {
        $statement = $this->connection->prepare(
            'SELECT page_key, content_json, revision, updated_by, updated_at
             FROM shepherd_website_content
             WHERE page_key = :page_key'
        );
        $statement->execute(['page_key' => $pageKey]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function replaceIfRevisionMatches(
        string $pageKey,
        string $contentJson,
        string $newRevision,
        string $expectedRevision,
        string $emptyRevision,
        int $userId
    ): ?array {
        $this->connection->beginTransaction();

        try {
            $insert = $this->connection->prepare(<<<'SQL'
INSERT IGNORE INTO shepherd_website_content
  (page_key, content_json, revision, updated_by)
VALUES
  (:page_key, '{}', :empty_revision, :updated_by)
SQL);
            $insert->execute([
                'page_key' => $pageKey,
                'empty_revision' => $emptyRevision,
                'updated_by' => $userId,
            ]);

            $select = $this->connection->prepare(
                'SELECT revision FROM shepherd_website_content WHERE page_key = :page_key FOR UPDATE'
            );
            $select->execute(['page_key' => $pageKey]);
            $currentRevision = (string) $select->fetchColumn();

            if (!hash_equals($currentRevision, $expectedRevision)) {
                $this->connection->rollBack();
                return null;
            }

            $update = $this->connection->prepare(<<<'SQL'
UPDATE shepherd_website_content
SET content_json = :content_json,
    revision = :revision,
    updated_by = :updated_by,
    updated_at = UTC_TIMESTAMP()
WHERE page_key = :page_key
SQL);
            $update->execute([
                'page_key' => $pageKey,
                'content_json' => $contentJson,
                'revision' => $newRevision,
                'updated_by' => $userId,
            ]);

            $snapshot = $this->connection->prepare(
                'SELECT page_key, content_json, revision, updated_by, updated_at
                 FROM shepherd_website_content
                 WHERE page_key = :page_key'
            );
            $snapshot->execute(['page_key' => $pageKey]);
            $savedRow = $snapshot->fetch(PDO::FETCH_ASSOC);
            if ($savedRow === false) {
                throw new \RuntimeException('The saved website content could not be read back.');
            }

            $this->connection->commit();
            return $savedRow;
        } catch (\Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }
    }
}
