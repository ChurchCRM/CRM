<?php

/**
 * scripts/test-geocoder-rest.php — a geocoding provider that stays unavailable
 * rests, so a request that geocodes many addresses waits for it once (#10886).
 *
 * Usage:
 *   php scripts/test-geocoder-rest.php
 *   npm run test:php
 *
 * A group map geocodes every member in one request. When a provider is still
 * unavailable after its retries, GeocoderChain must answer "unavailable" for the
 * next addresses without asking it, until REST_SECONDS (or the provider's
 * Retry-After, when longer) has passed, and must still ask the next provider.
 * A provider that answers is asked every time. No database or network is
 * needed: the providers are stand-ins and the test sets the clock.
 *
 * Exit codes:
 *   0  — every case passed
 *   1  — at least one case failed
 *   2  — vendor autoloader missing
 */

declare(strict_types=1);

use ChurchCRM\Service\Geocoding\GeocodeOutcome;
use ChurchCRM\Service\Geocoding\GeocoderChain;
use ChurchCRM\Service\Geocoding\GeocoderProviderInterface;
use ChurchCRM\Service\Geocoding\GeocodeResult;
use Psr\Log\NullLogger;

$autoload = __DIR__ . '/../src/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Missing {$autoload} — run `composer install` in src/ first.\n");
    exit(2);
}
require $autoload;

$passed = 0;
$failed = 0;

$check = function (bool $ok, string $label, string $detail = '') use (&$passed, &$failed): void {
    $n = $passed + $failed + 1;
    if ($ok) {
        $passed++;
        echo "ok {$n} - {$label}\n";
        return;
    }
    $failed++;
    echo "not ok {$n} - {$label}" . ($detail !== '' ? "  # {$detail}" : '') . "\n";
};

/** A provider that always gives the same answer and counts how often it is asked. */
final class StandInProvider implements GeocoderProviderInterface
{
    public int $calls = 0;

    public function __construct(private readonly string $name, private readonly GeocodeOutcome $answer)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function supports(?string $country): bool
    {
        return true;
    }

    public function geocode(string $street, ?string $city, ?string $state, ?string $zip, ?string $country, int $timeoutSeconds = 10): GeocodeOutcome
    {
        $this->calls++;

        return $this->answer;
    }
}

/** The chain with no waiting between retries and a clock the test moves. */
final class TestGeocoderChain extends GeocoderChain
{
    public static float $clock = 1000.0;

    protected function pause(float $seconds): void
    {
    }

    protected function now(): float
    {
        return self::$clock;
    }
}

$found = GeocodeOutcome::found(new GeocodeResult(40.7484, -73.9857, 'Up'));
$lookup = static fn (GeocoderChain $chain): GeocodeOutcome => $chain->lookup('350 5th Avenue', 'New York', 'NY', '10118', 'US');
$attempts = GeocoderChain::MAX_RETRIES + 1;

$down = new StandInProvider('Down', GeocodeOutcome::unavailable());
$chain = new TestGeocoderChain([$down], new NullLogger());
$check($lookup($chain)->isUnavailable() && $down->calls === $attempts, 'an unavailable provider is asked with its retries', "calls {$down->calls}");
$check($lookup($chain)->isUnavailable() && $down->calls === $attempts, 'the next address answers unavailable without asking it', "calls {$down->calls}");
TestGeocoderChain::$clock += GeocoderChain::REST_SECONDS + 1;
$lookup($chain);
$check($down->calls === 2 * $attempts, 'after REST_SECONDS it is asked again', "calls {$down->calls}");

$limited = new StandInProvider('Limited', GeocodeOutcome::unavailable(300));
$chain = new TestGeocoderChain([$limited], new NullLogger());
$lookup($chain);
$asked = $limited->calls;
TestGeocoderChain::$clock += GeocoderChain::REST_SECONDS + 1;
$lookup($chain);
$check($asked > 0 && $limited->calls === $asked, 'a longer Retry-After keeps it resting past REST_SECONDS', "calls {$limited->calls}");
TestGeocoderChain::$clock += 300;
$lookup($chain);
$check($limited->calls > $asked, 'after its Retry-After it is asked again', "calls {$limited->calls}");

$first = new StandInProvider('FirstDown', GeocodeOutcome::unavailable());
$up = new StandInProvider('Up', $found);
$chain = new TestGeocoderChain([$first, $up], new NullLogger());
$lookup($chain);
$outcome = $lookup($chain);
$check($outcome->isFound() && $first->calls === $attempts && $up->calls === 2, 'a resting provider is skipped and the next one still answers', "first {$first->calls}, up {$up->calls}");

$total = $passed + $failed;
echo "1..{$total}\n";
echo "# {$total} cases, {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
