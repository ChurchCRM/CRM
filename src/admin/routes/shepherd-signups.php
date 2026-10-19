<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\UserService;
use ChurchCRM\Shepherd\SignupRequestRepository;
use ChurchCRM\Shepherd\SignupService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

$app->get('/shepherd/signup-requests', function (Request $request, Response $response): Response {
    $repository = new SignupRequestRepository();
    $repository->ensureSchema();
    return (new PhpRenderer(__DIR__ . '/../views/'))->render($response, 'shepherd-signup-requests.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => 'Shepherd Account Requests',
        'sPageSubtitle' => 'Approve only verified requests and assign the least-privileged access profile.',
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Admin'), '/admin/'],
            ['Account Requests'],
        ]),
        'requests' => $repository->listForReview(),
        'people' => (new UserService())->getAssignablePeople(),
        'notice' => InputUtils::sanitizeText((string) ($request->getQueryParams()['notice'] ?? '')),
        'error' => InputUtils::sanitizeText((string) ($request->getQueryParams()['error'] ?? '')),
    ]);
});

$app->post('/shepherd/signup-requests/{id}/approve', function (Request $request, Response $response, array $args): Response {
    $body = (array) $request->getParsedBody();
    try {
        (new SignupService())->approve(
            (int) $args['id'],
            InputUtils::sanitizeText((string) ($body['profile'] ?? 'self_service')),
            (int) ($body['existing_person_id'] ?? 0),
            (int) AuthenticationManager::getCurrentUser()->getId()
        );
        return shepherdAdminRedirect($response, 'Account approved; a single-use password setup link was sent.');
    } catch (\Throwable $exception) {
        return shepherdAdminRedirect($response, '', $exception->getMessage());
    }
})->add(new CSRFMiddleware('shepherd_approve'));

$app->post('/shepherd/signup-requests/{id}/reject', function (Request $request, Response $response, array $args): Response {
    $body = (array) $request->getParsedBody();
    try {
        $ok = (new SignupService())->reject(
            (int) $args['id'],
            (int) AuthenticationManager::getCurrentUser()->getId(),
            InputUtils::sanitizeText((string) ($body['reason'] ?? ''))
        );
        return shepherdAdminRedirect($response, $ok ? 'Account request rejected.' : '', $ok ? '' : 'The request could not be rejected.');
    } catch (\Throwable $exception) {
        return shepherdAdminRedirect($response, '', $exception->getMessage());
    }
})->add(new CSRFMiddleware('shepherd_reject'));

$app->post('/shepherd/signup-requests/{id}/resend-password', function (Request $request, Response $response, array $args): Response {
    try {
        $ok = (new SignupService())->resendPasswordSetup(
            (int) $args['id'],
            (int) AuthenticationManager::getCurrentUser()->getId()
        );
        return shepherdAdminRedirect($response, $ok ? 'A new password setup link was sent.' : '', $ok ? '' : 'The password setup link could not be renewed.');
    } catch (\Throwable $exception) {
        return shepherdAdminRedirect($response, '', $exception->getMessage());
    }
})->add(new CSRFMiddleware('shepherd_resend_password'));

function shepherdAdminRedirect(Response $response, string $notice = '', string $error = ''): Response
{
    $query = http_build_query(array_filter(['notice' => $notice, 'error' => $error], static fn ($value) => $value !== ''));
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/admin/shepherd/signup-requests' . ($query === '' ? '' : '?' . $query))
        ->withStatus(303);
}
