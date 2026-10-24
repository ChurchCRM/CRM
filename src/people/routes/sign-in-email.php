<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Slim\Middleware\Request\Auth\EditRecordsRoleAuthMiddleware;
use ChurchCRM\Utils\CSRFUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// "Email needs attention" for staff with Edit Records (member sign-in design
// §9, §11.2): the Admin → Member Portal list is for administrators only, but
// §9 lets Edit Records staff confirm moved family emails and fix shared ones.
$app->get('/sign-in-email', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'sign-in-email.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Email needs attention'),
        'sPageSubtitle' => gettext('The sign-in rules let them in, but their email does not: they have none, share it with someone else who may sign in, or it is not confirmed yet.'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('People'), '/people/dashboard'],
            [gettext('Email needs attention')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Member Portal'), 'url' => '/admin/member-portal#portal-members', 'icon' => 'fa-house-user'],
        ]),
        'csrfToken' => CSRFUtils::generateToken(),
    ]);
})->add(EditRecordsRoleAuthMiddleware::class);
