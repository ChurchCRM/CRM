<?php

namespace ChurchCRM\Slim\Middleware\Api;

use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Families have no email of their own since 7.8.3 (member sign-in design §10).
 * For that one release, the endpoints that returned a family's email still
 * return one, read-only: the head of household's, else the spouse's. Family
 * serialization includes it only while this middleware handles the request.
 * It marks those responses deprecated (RFC 9745) and logs a client that still
 * sends a family email, which is not stored.
 */
class FamilyEmailDeprecationMiddleware implements MiddlewareInterface
{
    /** 2026-10-17T00:00:00Z, when the family email was deprecated. */
    public const DEPRECATED_AT = 1792195200;

    private const DOCS_URL = 'https://docs.churchcrm.io/api/';

    private static bool $handling = false;

    /** @var array<int, string> the deprecated email by family id, for the current request */
    private static array $emails = [];

    /**
     * @param string      $docsPage     the endpoint's page under DOCS_URL, named from its operationId
     * @param string|null $writtenField the request field that used to set the family email
     */
    public function __construct(
        private readonly string $docsPage,
        private readonly ?string $writtenField = null
    ) {
    }

    public static function isHandling(): bool
    {
        return self::$handling;
    }

    public static function emailOf(Family $family): string
    {
        return self::$emails[(int) $family->getId()] ??= (string) $family->getEmailRecipient()?->getEmail();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $body = $request->getParsedBody();
        if ($this->writtenField !== null && is_array($body) && array_key_exists($this->writtenField, $body)) {
            LoggerUtils::getAppLogger()->warning(sprintf(
                'Ignored the family email sent to %s %s: families have no email since 7.8.3. Set a person\'s email instead.',
                $request->getMethod(),
                $request->getUri()->getPath()
            ));
        }

        self::$handling = true;
        try {
            $response = $handler->handle($request);
        } finally {
            self::$handling = false;
            self::$emails = [];
        }

        return $response
            ->withHeader('Deprecation', '@' . self::DEPRECATED_AT)
            ->withAddedHeader('Link', '<' . self::DOCS_URL . $this->docsPage . '>; rel="deprecation"; type="text/html"');
    }
}
