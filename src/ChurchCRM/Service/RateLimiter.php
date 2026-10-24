<?php

namespace ChurchCRM\Service;

use ChurchCRM\model\ChurchCRM\RateLimit;
use ChurchCRM\model\ChurchCRM\RateLimitQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;

/**
 * Counts attempts per key in a bucket, allowing `limit` attempts in a window
 * that starts at the first attempt and lasts `windowSeconds`.
 *
 *     $perAddress = new RateLimiter('portal-signin.address', 5, 3600);
 *     if (!$perAddress->attempt($typedEmail)) {
 *         // over the limit: answer as usual, send nothing
 *     }
 *
 * Keys are trimmed and lower-cased, then stored only as a SHA-256 hash. A row
 * is deleted once its window has ended. A limit below 1 never throttles.
 */
final class RateLimiter
{
    private const MAX_WRITE_TRIES = 5;

    public function __construct(
        private readonly string $bucket,
        private readonly int $limit,
        private readonly int $windowSeconds
    ) {
        if ($this->windowSeconds < 1) {
            throw new \InvalidArgumentException('A rate limit window must be at least one second');
        }
    }

    public function tooManyAttempts(string $key): bool
    {
        if ($this->limit < 1) {
            return false;
        }
        $row = $this->findLive($key);

        return $row instanceof RateLimit && $row->getHits() >= $this->limit;
    }

    /**
     * Records one attempt, even when the key is already over its limit. The
     * window is not extended.
     *
     * @return int attempts in the current window, this one included
     */
    public function hit(string $key): int
    {
        if ($this->limit < 1) {
            return 0;
        }

        return $this->record($key, false) ?? 0;
    }

    /**
     * Records one attempt when the key is under its limit.
     *
     * @return bool false, with nothing recorded, when the key is over its limit
     */
    public function attempt(string $key): bool
    {
        if ($this->limit < 1) {
            return true;
        }

        return $this->record($key, true) !== null;
    }

    public function clear(string $key): void
    {
        RateLimitQuery::create()
            ->filterByBucket($this->bucket)
            ->filterByKeyHash(self::hashKey($key))
            ->delete();
    }

    /**
     * Seconds until the key's window ends, or 0 when it has none.
     */
    public function availableIn(string $key): int
    {
        $row = $this->findLive($key);

        return $row instanceof RateLimit ? max(0, $row->getResetAt() - time()) : 0;
    }

    /**
     * The address a throttle should count for the current request. IPv6 clients
     * are counted by their /64, which one subscriber usually holds whole.
     */
    public static function clientIpKey(): string
    {
        $address = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            return $address;
        }
        $prefix = substr((string) inet_pton($address), 0, 8) . str_repeat("\0", 8);

        return inet_ntop($prefix) . '/64';
    }

    /**
     * @return int|null attempts in the window after recording, or null when
     *                  $onlyUnderLimit refused an attempt over the limit
     */
    private function record(string $key, bool $onlyUnderLimit): ?int
    {
        $hash = self::hashKey($key);

        for ($try = 0; $try < self::MAX_WRITE_TRIES; $try++) {
            $now = time();
            RateLimitQuery::create()->filterByResetAt($now, Criteria::LESS_EQUAL)->delete();

            $row = RateLimitQuery::create()
                ->filterByBucket($this->bucket)
                ->filterByKeyHash($hash)
                ->findOne();

            if (!$row instanceof RateLimit) {
                try {
                    (new RateLimit())
                        ->setBucket($this->bucket)
                        ->setKeyHash($hash)
                        ->setHits(1)
                        ->setResetAt($now + $this->windowSeconds)
                        ->save();

                    return 1;
                } catch (PropelException) {
                    continue;
                }
            }

            $seen = $row->getHits();
            if ($onlyUnderLimit && $seen >= $this->limit) {
                return null;
            }

            // A compare-and-set on the hit count keeps concurrent requests from losing a hit.
            $updated = RateLimitQuery::create()
                ->filterById($row->getId())
                ->filterByHits($seen)
                ->update(['Hits' => $seen + 1]);
            if ($updated === 1) {
                return $seen + 1;
            }
        }

        throw new \RuntimeException('Could not record a rate limit attempt for ' . $this->bucket);
    }

    private function findLive(string $key): ?RateLimit
    {
        return RateLimitQuery::create()
            ->filterByBucket($this->bucket)
            ->filterByKeyHash(self::hashKey($key))
            ->filterByResetAt(time(), Criteria::GREATER_THAN)
            ->findOne();
    }

    private static function hashKey(string $key): string
    {
        return hash('sha256', mb_strtolower(trim($key)));
    }
}
