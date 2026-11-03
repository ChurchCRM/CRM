<?php

declare(strict_types=1);

use ChurchCRM\Service\Geocoding\GeocodeOutcome;
use ChurchCRM\Service\Geocoding\GeocoderChain;
use ChurchCRM\Service\Geocoding\GeocoderProviderInterface;
use ChurchCRM\Service\Geocoding\GeocodeResult;
use Psr\Log\NullLogger;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

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
Assert::true($lookup($chain)->isUnavailable());
Assert::same($attempts, $down->calls, 'Unavailable provider is retried');
Assert::true($lookup($chain)->isUnavailable());
Assert::same($attempts, $down->calls, 'Next address reuses the unavailable result');
TestGeocoderChain::$clock += GeocoderChain::REST_SECONDS + 1;
$lookup($chain);
Assert::same(2 * $attempts, $down->calls, 'Provider is retried after resting');

$limited = new StandInProvider('Limited', GeocodeOutcome::unavailable(300));
$chain = new TestGeocoderChain([$limited], new NullLogger());
$lookup($chain);
$asked = $limited->calls;
Assert::true($asked > 0);
TestGeocoderChain::$clock += GeocoderChain::REST_SECONDS + 1;
$lookup($chain);
Assert::same($asked, $limited->calls, 'Longer Retry-After controls the rest');
TestGeocoderChain::$clock += 300;
$lookup($chain);
Assert::true($limited->calls > $asked, 'Provider is retried after Retry-After');

$first = new StandInProvider('FirstDown', GeocodeOutcome::unavailable());
$up = new StandInProvider('Up', $found);
$chain = new TestGeocoderChain([$first, $up], new NullLogger());
$lookup($chain);
$outcome = $lookup($chain);
Assert::true($outcome->isFound());
Assert::same($attempts, $first->calls, 'Resting provider is skipped');
Assert::same(2, $up->calls, 'Available provider is asked for every address');
