<?php

/**
 * scripts/test-geocoder-chain.php — how GeocoderChain treats an unavailable service (#10791).
 *
 * Usage:
 *   php scripts/test-geocoder-chain.php
 *   npm run test:php
 *
 * Stub providers stand in for the public services, so no network or database is needed.
 * A transient failure is retried and then succeeds; every provider unavailable is reported
 * as unavailable, not as not found; not found stays distinct and is not retried; an
 * unavailable provider falls through to the next one; retries stay inside the time budget.
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

/** Plays back a fixed list of outcomes, repeating the last one. */
final class StubProvider implements GeocoderProviderInterface
{
    public int $calls = 0;

    /** @param GeocodeOutcome[] $script */
    public function __construct(private string $name, private array $script)
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
        $outcome = $this->script[min($this->calls, \count($this->script) - 1)];
        $this->calls++;

        return $outcome;
    }
}

/** A chain that records its backoff instead of sleeping. */
final class FastChain extends GeocoderChain
{
    /** @var float[] */
    public array $pauses = [];

    protected function pause(float $seconds): void
    {
        $this->pauses[] = $seconds;
    }
}

$hit = GeocodeOutcome::found(new GeocodeResult(40.7484, -73.9857, 'Stub'));
$down = GeocodeOutcome::unavailable();
$none = GeocodeOutcome::notFound();

$lookup = static function (array $providers) {
    $chain = new FastChain($providers, new NullLogger());

    return [$chain->lookup('350 5th Ave', 'New York', 'NY', '10118', 'US'), $chain];
};

[$outcome, $chain] = $lookup([$a = new StubProvider('A', [$down, $hit])]);
$check($outcome->isFound() && $a->calls === 2, 'a transient failure is retried and then succeeds', "status={$outcome->status} calls={$a->calls}");
$check(\count($chain->pauses) === 1 && $chain->pauses[0] >= 1.0, 'the backoff waits at least one second (Nominatim policy)');

[$outcome] = $lookup([$a = new StubProvider('A', [$down])]);
$check($outcome->isUnavailable() && $a->calls === GeocoderChain::MAX_RETRIES + 1, 'a provider that stays down is retried a bounded number of times', "calls={$a->calls}");

[$outcome, $chain] = $lookup([$a = new StubProvider('A', [GeocodeOutcome::unavailable(5), $hit])]);
$check($outcome->isFound() && $chain->pauses[0] >= 5.0, 'Retry-After sets the wait');

[$outcome, $chain] = $lookup([$a = new StubProvider('A', [GeocodeOutcome::unavailable(60)])]);
$check($outcome->isUnavailable() && $a->calls === 1 && $chain->pauses === [], 'a Retry-After beyond the time budget is not waited for');

[$outcome] = $lookup([$a = new StubProvider('A', [$none])]);
$check($outcome->status === GeocodeOutcome::NOT_FOUND && $a->calls === 1, 'not found is distinct from unavailable and is not retried');

[$outcome] = $lookup([new StubProvider('A', [$down]), $b = new StubProvider('B', [$hit])]);
$check($outcome->isFound() && $b->calls === 1, 'an unavailable provider falls through to the next one');

[$outcome] = $lookup([new StubProvider('A', [$down]), new StubProvider('B', [$none])]);
$check($outcome->isUnavailable(), 'unavailable wins over not found when no provider found the address');

[$outcome] = $lookup([new StubProvider('A', [$down]), new StubProvider('B', [$down])]);
$check($outcome->isUnavailable() && $outcome->result === null, 'all providers unavailable is reported as unavailable');

[$outcome] = $lookup([new StubProvider('A', [$none]), new StubProvider('B', [$none])]);
$check($outcome->status === GeocodeOutcome::NOT_FOUND, 'all providers saying not found is not found');

$chain = new FastChain([new StubProvider('A', [$down]), new StubProvider('B', [$hit])], new NullLogger());
$check($chain->geocode('x', null, null, null, 'US') !== null, 'geocode() still returns the result after a fallthrough');

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
