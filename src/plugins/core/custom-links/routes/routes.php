<?php

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\MenuLink;
use ChurchCRM\model\ChurchCRM\MenuLinkQuery;
use ChurchCRM\Plugins\CustomLinks\CustomLinksPlugin;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Get plugin instance
$plugin = CustomLinksPlugin::getInstance();
if ($plugin === null) {
    return;
}

// MVC Route - Management page (admin only)
$app->get('/custom-links/manage', function (Request $request, Response $response) use ($plugin): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');

    return $renderer->render($response, 'manage.php', [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Custom Menu Links'),
        'linkCount' => $plugin->getLinkCount(),
    ]);
})->add(AdminRoleAuthMiddleware::class);

// API Routes (admin only)
$app->group('/custom-links/api', function (RouteCollectorProxy $group) use ($plugin): void {
    // GET /plugins/custom-links/api/links - List all links
    $group->get('/links', function (Request $request, Response $response) use ($plugin): Response {
        $links = $plugin->getMenuLinks();

        return SlimUtils::renderJSON($response, ['success' => true, 'data' => $links]);
    });

    // POST /plugins/custom-links/api/links - Create new link
    $group->post('/links', function (Request $request, Response $response): Response {
        $data = $request->getParsedBody();

        // Validation
        $errors = [];

        // Validate Name
        if (empty($data['Name']) || trim($data['Name']) === '') {
            $errors[] = gettext('Menu name is required');
        } elseif (strlen(trim($data['Name'])) < 2) {
            $errors[] = gettext('Menu name must be at least 2 characters');
        } elseif (strlen(trim($data['Name'])) > 50) {
            $errors[] = gettext('Menu name must be 50 characters or less');
        } elseif (preg_match('/<[^>]*>/', $data['Name'])) {
            $errors[] = gettext('Menu name cannot contain HTML tags');
        }

        // Validate Uri
        if (empty($data['Uri']) || trim($data['Uri']) === '') {
            $errors[] = gettext('Link address is required');
        } elseif (!preg_match('/^https?:\/\//i', $data['Uri'])) {
            $errors[] = gettext('Link must start with http:// or https://');
        } elseif (!preg_match('/^https?:\/\/[^\s\/$.?#].[^\s]*$/i', $data['Uri'])) {
            $errors[] = gettext('Link must be a valid URL');
        } elseif (preg_match('/<[^>]*>/', $data['Uri'])) {
            $errors[] = gettext('Link address cannot contain HTML tags');
        }

        if (!empty($errors)) {
            return SlimUtils::renderJSON(
                $response,
                [
                    'success' => false,
                    'message' => gettext('Validation Error'),
                    'errors' => $errors,
                ],
                400
            );
        }

        try {
            $link = new MenuLink();
            $link->setName(trim($data['Name']));
            $link->setUri(trim($data['Uri']));
            $link->setOrder($data['Order'] ?? 0);
            $link->save();

            return SlimUtils::renderJSON($response, [
                'success' => true,
                'data' => $link->toArray(),
            ]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to create link'), [], 500, $e, $request);
        }
    });

    // DELETE /plugins/custom-links/api/links/{id} - Delete link
    $group->delete('/links/{id:[0-9]+}', function (Request $request, Response $response, array $args): Response {
        $link = MenuLinkQuery::create()->findPk((int) $args['id']);

        if ($link === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Link not found'), [], 404);
        }

        try {
            $link->delete();

            return SlimUtils::renderJSON($response, ['success' => true]);
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Failed to delete link'), [], 500, $e, $request);
        }
    });
})->add(AdminRoleAuthMiddleware::class);
