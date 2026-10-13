<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\data\GradePresets;
use ChurchCRM\Service\GradePresetChangedException;
use ChurchCRM\Service\GradePromotionService;
use ChurchCRM\Service\GradeService;
use ChurchCRM\Slim\Middleware\CSRFMiddleware;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/grades', function (RouteCollectorProxy $group): void {
    $group->get('/promotion', function (Request $request, Response $response): Response {
        return SlimUtils::renderJSON($response, GradePromotionService::status());
    });

    $group->post('/promotion', function (Request $request, Response $response): Response {
        $input = (array) $request->getParsedBody();
        $result = GradePromotionService::promoteNow(
            (int) AuthenticationManager::getCurrentUser()->getId(),
            (string) ($input['expectedLastAt'] ?? '')
        );
        if ($result === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Grades were promoted by someone else in the meantime. Reload the page to see the new grades.'), [], 409);
        }

        return SlimUtils::renderJSON($response, ['result' => $result, 'status' => GradePromotionService::status()]);
    });

    $group->get('/presets', function (Request $request, Response $response): Response {
        $presets = [];
        foreach (GradePresets::all() as $key => $preset) {
            $presets[] = ['key' => $key, 'name' => $preset['name'], 'grades' => array_values($preset['grades'])];
        }

        return SlimUtils::renderJSON($response, ['current' => GradeService::currentPresetKey(), 'presets' => $presets]);
    });

    $group->get('/presets/{key:[a-z-]+}/preview', function (Request $request, Response $response, array $args): Response {
        if (GradePresets::get($args['key']) === null) {
            throw new HttpNotFoundException($request, gettext('Grade names not found'));
        }

        return SlimUtils::renderJSON($response, GradeService::applyPreset($args['key'], (int) AuthenticationManager::getCurrentUser()->getId(), true));
    });

    $group->post('/presets/{key:[a-z-]+}', function (Request $request, Response $response, array $args): Response {
        if (GradePresets::get($args['key']) === null) {
            throw new HttpNotFoundException($request, gettext('Grade names not found'));
        }

        $input = (array) $request->getParsedBody();
        try {
            $summary = GradeService::applyPreset(
                $args['key'],
                (int) AuthenticationManager::getCurrentUser()->getId(),
                false,
                (string) ($input['expectedState'] ?? '')
            );
        } catch (GradePresetChangedException) {
            return SlimUtils::renderErrorJSON($response, gettext('The grades changed after the preview. Review the changes again before applying them.'), [], 409);
        }

        return SlimUtils::renderJSON($response, $summary);
    });
})->add(new CSRFMiddleware());
