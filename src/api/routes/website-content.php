<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Shepherd\WebsiteContentService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpForbiddenException;
use Slim\Routing\RouteCollectorProxy;

$app->get(
    '/public/website-content/{pageKey:[a-z0-9][a-z0-9-]{0,63}}',
    'getPublicWebsiteContent'
);

$app->get('/background/website-content/session', 'getWebsiteEditorSession')
    ->add(AdminRoleAuthMiddleware::class);

$app->group('/website-content', function (RouteCollectorProxy $group): void {
    $group->put('/{pageKey:[a-z0-9][a-z0-9-]{0,63}}', 'updateWebsiteContent')
        ->add(new CSRFMiddleware('website_content_editor'));
})->add(AdminRoleAuthMiddleware::class);

function noStoreWebsiteContentResponse(Response $response): Response
{
    return $response
        ->withHeader('Cache-Control', 'no-store, private')
        ->withHeader('Pragma', 'no-cache');
}

function getPublicWebsiteContent(Request $request, Response $response, array $args): Response
{
    try {
        $document = (new WebsiteContentService())->getDocument((string) $args['pageKey']);
    } catch (\InvalidArgumentException $exception) {
        throw new HttpBadRequestException($request, $exception->getMessage());
    }

    return noStoreWebsiteContentResponse(SlimUtils::renderJSON($response, $document));
}

function getWebsiteEditorSession(Request $request, Response $response, array $args): Response
{
    requireWebsiteEditorBrowserSession($request);
    $user = AuthenticationManager::getCurrentUser();

    return noStoreWebsiteContentResponse(SlimUtils::renderJSON($response, [
        'canEdit' => true,
        'displayName' => $user->getFullName(),
        'CSRFToken' => CSRFUtils::generateToken('website_content_editor'),
    ]));
}

function updateWebsiteContent(Request $request, Response $response, array $args): Response
{
    requireWebsiteEditorBrowserSession($request);
    $body = $request->getParsedBody();
    if (!is_array($body)
        || array_diff(array_keys($body), ['content', 'revision']) !== []
        || count($body) !== 2
        || !array_key_exists('content', $body)
        || !isset($body['revision'])
        || !is_string($body['revision'])) {
        throw new HttpBadRequestException($request, 'Content and its current revision are required.');
    }

    $user = AuthenticationManager::getCurrentUser();
    try {
        $result = (new WebsiteContentService())->updateDocument(
            (string) $args['pageKey'],
            $body['content'],
            $body['revision'],
            (int) $user->getId()
        );
    } catch (\InvalidArgumentException $exception) {
        throw new HttpBadRequestException($request, $exception->getMessage());
    }

    if ($result['conflict']) {
        return noStoreWebsiteContentResponse(SlimUtils::renderJSON($response->withStatus(409), [
            'code' => 'revision_conflict',
            'message' => 'This page was updated by another administrator. Reload before saving again.',
            'document' => $result['document'],
        ]));
    }

    try {
        LoggerUtils::getAppLogger()->info('Website page text updated', [
            'page' => $args['pageKey'],
            'userId' => $user->getId(),
            'revision' => $result['document']['revision'],
            'valueCount' => count((array) $result['document']['content']),
        ]);
    } catch (\Throwable) {
        // The committed content update remains successful if optional application logging fails.
    }

    return noStoreWebsiteContentResponse(SlimUtils::renderJSON($response, $result['document']));
}

function requireWebsiteEditorBrowserSession(Request $request): void
{
    if (!AuthenticationManager::isCompletedLocalAuthentication()) {
        throw new HttpForbiddenException(
            $request,
            'Website editing requires a signed-in Shepherd browser session.'
        );
    }
}
