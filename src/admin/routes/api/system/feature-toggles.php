<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\ConfigQuery;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// POST /api/system/feature-toggles
// Save feature toggle settings
$app->post('/api/system/feature-toggles', function (Request $request, Response $response): Response {
    $data = $request->getParsedBody();

    if (empty($data) || !is_array($data)) {
        return SlimUtils::renderErrorJSON($response, 'Invalid request data', 400);
    }

    try {
        foreach ($data as $settingKey => $value) {
            // Validate setting exists in config
            try {
                $configItem = SystemConfig::getConfigItem($settingKey);
            } catch (\Exception $e) {
                continue;
            }

            if (!$configItem) {
                continue;
            }

            // Update the config value
            $config = ConfigQuery::create()->findOneByName($settingKey);
            if (!$config) {
                $config = new \ChurchCRM\model\ChurchCRM\Config();
                $config->setName($settingKey);
            }

            $config->setValue($value ? '1' : '0')->save();
        }

        return SlimUtils::renderJSON($response, ['success' => true]);
    } catch (\Exception $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), 500);
    }
});
