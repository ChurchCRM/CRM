<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\Middleware\AuthMiddleware;
use ChurchCRM\Slim\Middleware\Request\Auth\ManageGroupRoleAuthMiddleware;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Admin routes group - requires authentication and admin role
$app->group('/admin', function (RouteCollectorProxy $group): void {
    $handler = function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../views/');

        $pageArgs = [
            'sRootPath'    => SystemURLs::getRootPath(),
            'sPageTitle'   => gettext('Kiosk Manager'),
            'sPageSubtitle' => gettext('Register and manage kiosk devices for event check-in'),
            'aBreadcrumbs' => PageHeader::breadcrumbs([
                [gettext('Groups'), '/groups/dashboard'],
                [gettext('Kiosk Manager')],
            ]),
        ];

        // The settings API is admin-only, so ManageGroups users get no settings card.
        $pageArgs['kioskSettings'] = [];
        if (AuthenticationManager::getCurrentUser()->isAdmin()) {
            $classificationChoices = array_map(
                static fn ($option): array => ['value' => (string) $option->getOptionId(), 'label' => $option->getOptionName()],
                iterator_to_array(ListOptionQuery::create()->filterById(1)->orderByOptionSequence()->find()),
            );
            $pageArgs['kioskSettings'] = array_map(function (array $setting) use ($classificationChoices): array {
                $setting['label'] = gettext('Guest Classification');
                $setting['choices'] = $classificationChoices;

                return $setting;
            }, SystemConfig::getSettingsConfig(['iKioskGuestClassification']));
        }

        return $renderer->render($response, 'manager.php', $pageArgs);
    };

    $group->get('', $handler);
    $group->get('/', $handler);
})->add(ManageGroupRoleAuthMiddleware::class)->add(AuthMiddleware::class);
