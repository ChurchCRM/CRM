<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\CommunityFeedbackService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\PhpRenderer;

// Redirect /community root to the feedback page
$app->get('/', function (Request $request, Response $response): Response {
    return $response
        ->withHeader('Location', SystemURLs::getRootPath() . '/community/feedback')
        ->withStatus(302);
});

$app->get('/feedback', function (Request $request, Response $response): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    $pageArgs = [
        'sRootPath'       => SystemURLs::getRootPath(),
        'sPageTitle'      => gettext('We Need Your Help'),
        'aBreadcrumbs'    => PageHeader::breadcrumbs([
            [gettext('Community')],
            [gettext('We Need Your Help')],
        ]),
        'feedbackItems'   => CommunityFeedbackService::getItems(),
        'sDiscussionsUrl' => 'https://github.com/ChurchCRM/CRM/discussions',
    ];

    return $renderer->render($response, 'feedback.php', $pageArgs);
});
