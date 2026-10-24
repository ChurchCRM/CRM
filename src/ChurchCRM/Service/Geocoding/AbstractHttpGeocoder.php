<?php

namespace ChurchCRM\Service\Geocoding;

use ChurchCRM\Utils\LoggerUtils;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Shared HTTP plumbing for geocoders that call a JSON web service.
 */
abstract class AbstractHttpGeocoder implements GeocoderProviderInterface
{
    protected LoggerInterface $logger;

    public function __construct()
    {
        $this->logger = LoggerUtils::getAppLogger() ?? new NullLogger();
    }

    public function supports(?string $country): bool
    {
        return true;
    }

    /**
     * GET a JSON document. Never throws: a transport error, a non-2xx status or
     * an unparseable body is logged and reported through the response.
     *
     * @param string[] $headers extra request headers, e.g. ["User-Agent: ..."]
     */
    protected function fetchJson(string $url, array $headers = [], int $timeoutSeconds = 10): GeocoderHttpResponse
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => $headers === [] ? '' : implode("\r\n", $headers) . "\r\n",
                'timeout'       => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = self::statusFrom($responseHeaders);

        if ($body === false || $status === 0) {
            $this->logger->warning('Geocoding: ' . $this->getName() . ' request failed (no response)');
            return new GeocoderHttpResponse(0, null);
        }

        $retryAfter = self::retryAfterFrom($responseHeaders);
        if ($status < 200 || $status >= 300) {
            $this->logger->warning('Geocoding: ' . $this->getName() . ' answered HTTP ' . $status);
            return new GeocoderHttpResponse($status, null, $retryAfter);
        }

        $decoded = json_decode($body, true, 512);
        if (!\is_array($decoded)) {
            $this->logger->warning('Geocoding: ' . $this->getName() . ' returned an unreadable response');
            return new GeocoderHttpResponse($status, null, $retryAfter);
        }

        return new GeocoderHttpResponse($status, $decoded, $retryAfter);
    }

    /**
     * Outcome for a response that carries no usable data: unavailable on a
     * transport failure, 429, 5xx or garbled body; otherwise not found.
     */
    protected function failureOutcome(GeocoderHttpResponse $response): GeocodeOutcome
    {
        return $response->isUnavailable()
            ? GeocodeOutcome::unavailable($response->retryAfterSeconds)
            : GeocodeOutcome::notFound();
    }

    /**
     * @param string[] $responseHeaders
     */
    private static function statusFrom(array $responseHeaders): int
    {
        // Redirects add one status line each; the last one is the final answer.
        $status = 0;
        foreach ($responseHeaders as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }

    /**
     * @param string[] $responseHeaders
     */
    private static function retryAfterFrom(array $responseHeaders): ?int
    {
        foreach (array_reverse($responseHeaders) as $line) {
            if (stripos($line, 'Retry-After:') !== 0) {
                continue;
            }
            $value = trim(substr($line, \strlen('Retry-After:')));
            if (ctype_digit($value)) {
                return (int) $value;
            }
            $date = strtotime($value);

            return $date === false ? null : max(0, $date - time());
        }

        return null;
    }
}
