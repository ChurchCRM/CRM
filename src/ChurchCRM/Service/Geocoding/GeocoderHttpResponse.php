<?php

namespace ChurchCRM\Service\Geocoding;

/**
 * One HTTP exchange with a geocoding service. $data is the decoded JSON body
 * when the service answered; null when the request failed.
 */
final class GeocoderHttpResponse
{
    /**
     * @param array<mixed>|null $data
     */
    public function __construct(
        public readonly int $status,
        public readonly ?array $data,
        public readonly ?int $retryAfterSeconds = null,
    ) {
    }

    /**
     * Timeouts and connection errors (status 0), 429 and 5xx, and a 2xx body that
     * is not JSON. Other 4xx answers are the service refusing this request, not an outage.
     */
    public function isUnavailable(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500 || ($this->status < 300 && $this->data === null);
    }
}
