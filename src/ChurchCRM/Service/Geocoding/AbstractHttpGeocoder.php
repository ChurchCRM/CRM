<?php

namespace ChurchCRM\Service\Geocoding;

use ChurchCRM\Utils\LoggerUtils;
use Psr\Log\LoggerInterface;

/**
 * Shared HTTP plumbing for geocoders that call a JSON web service.
 */
abstract class AbstractHttpGeocoder implements GeocoderProviderInterface
{
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger();
    }

    public function supports(?string $country): bool
    {
        return true;
    }

    /**
     * GET a JSON document. Returns null (and logs) on a transport error or
     * an unparseable body, so a provider never throws into the chain.
     *
     * @param string[] $headers extra request headers, e.g. ["User-Agent: ..."]
     * @return array<mixed>|null
     */
    protected function fetchJson(string $url, array $headers = [], int $timeoutSeconds = 10): ?array
    {
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => $headers === [] ? '' : implode("\r\n", $headers) . "\r\n",
                'timeout' => $timeoutSeconds,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            $this->logger->warning('Geocoding: ' . $this->getName() . ' request failed');
            return null;
        }

        $decoded = json_decode($response, true, 512);
        if (!\is_array($decoded)) {
            $this->logger->warning('Geocoding: ' . $this->getName() . ' returned an unreadable response');
            return null;
        }

        return $decoded;
    }
}
