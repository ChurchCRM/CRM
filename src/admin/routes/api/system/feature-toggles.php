<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// GET /admin/api/system/feature-toggles?settings=bEnabledFinance,bEnabledEvents
$app->get('/api/system/feature-toggles', function (Request $request, Response $response): Response {
    $names = array_filter(array_map('trim', explode(',', (string) ($request->getQueryParams()['settings'] ?? ''))));
    if ($names === []) {
        return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400);
    }

    $toggles = [];
    foreach ($names as $settingKey) {
        try {
            $isBoolean = SystemConfig::getConfigItem($settingKey)->getType() === 'boolean';
        } catch (\Throwable) {
            $isBoolean = false;
        }
        if (!$isBoolean) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid setting') . ': ' . $settingKey, [], 400);
        }
        $toggles[$settingKey] = SystemConfig::getBooleanValue($settingKey);
    }

    return SlimUtils::renderJSON($response, ['toggles' => $toggles]);
});

// POST /admin/api/system/feature-toggles
$app->post('/api/system/feature-toggles', function (Request $request, Response $response): Response {
    $data = $request->getParsedBody();

    if (empty($data) || !is_array($data)) {
        return SlimUtils::renderErrorJSON($response, gettext('Invalid request data'), [], 400);
    }

    $toggles = [];
    foreach ($data as $settingKey => $value) {
        try {
            $isBoolean = SystemConfig::getConfigItem((string) $settingKey)->getType() === 'boolean';
        } catch (\Throwable) {
            $isBoolean = false;
        }
        if (!$isBoolean) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid setting') . ': ' . $settingKey, [], 400);
        }
        $toggles[$settingKey] = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
    }

    foreach ($toggles as $settingKey => $value) {
        SystemConfig::setValue($settingKey, $value);
    }

    return SlimUtils::renderJSON($response, ['success' => true]);
});
