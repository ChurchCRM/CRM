<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Finance-role REST API for statement-related SystemConfig keys.
 *
 * All routes require FinanceRoleAuth (Finance role or Admin), enforced by
 * FinanceRoleAuthMiddleware at the module level in src/finance/index.php.
 *
 * Mounted at /finance/api/config/{configName} via src/finance/index.php.
 */

$allowedKeys = [
    'sTaxReport1',
    'sTaxReport2',
    'sTaxReport3',
    'sTaxSigner',
    'sChurchTaxId',
    'iPDFOutputType',
];

$app->group('/api/config/{configName}', function (RouteCollectorProxy $group) use ($allowedKeys): void {

    $group->get('', function (Request $request, Response $response, array $args) use ($allowedKeys): Response {
        $configName = $args['configName'];

        if (!in_array($configName, $allowedKeys, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Configuration item not found'), [], 404, null, $request);
        }

        $configItem = SystemConfig::getConfigItem($configName);
        if ($configItem === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Configuration item not found'), [], 404, null, $request);
        }

        return SlimUtils::renderJSON($response, ['value' => SystemConfig::getValue($configName)]);
    });

    $group->post('', function (Request $request, Response $response, array $args) use ($allowedKeys): Response {
        $configName = $args['configName'];

        if (!in_array($configName, $allowedKeys, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Configuration item not found'), [], 404, null, $request);
        }

        $configItem = SystemConfig::getConfigItem($configName);
        if ($configItem === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Configuration item not found'), [], 404, null, $request);
        }

        $input = $request->getParsedBody();
        $value = $input['value'] ?? '';

        $blankAllowed = $value === '' && $configItem->getDefault() === '';
        if ($configItem->getType() === 'number' && !is_numeric($value) && !$blankAllowed) {
            return SlimUtils::renderErrorJSON($response, gettext('This setting must be a number'), [], 400, null, $request);
        }

        SystemConfig::setValue($configName, $value);

        return SlimUtils::renderJSON($response, ['value' => SystemConfig::getValue($configName)]);
    });
});
