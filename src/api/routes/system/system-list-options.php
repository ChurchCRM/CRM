<?php

use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/system/list-options', function (RouteCollectorProxy $group): void {
    // Helper function to get list options by ID
    $getListOptions = function (int $listId): array {
        // Restricted list IDs that should not be exposed via API
        $restrictedLists = [
            5, // Security permissions (bAll, bAdmin, etc.)
        ];
        
        if (in_array($listId, $restrictedLists, true)) {
            return [
                'error' => 'Access to this list is restricted',
                'status' => 403
            ];
        }
        
        $options = ListOptionQuery::create()
            ->filterById($listId)
            ->orderByOptionSequence()
            ->find();
        
        if ($options->count() === 0) {
            return [
                'error' => "No options found for list ID $listId",
                'status' => 404
            ];
        }
        
        return [
            'data' => $options->toArray(),
            'status' => 200
        ];
    };
    
    // Convenience endpoint for deposit types
    $group->get('/deposit-types', function (Request $request, Response $response, array $args): Response {
        // Hardcoded deposit types (database list IDs are unreliable across installations)
        $depositTypes = [
            ['OptionId' => 1, 'OptionName' => 'Bank', 'OptionSequence' => 1],
            ['OptionId' => 2, 'OptionName' => 'Cash', 'OptionSequence' => 2],
            ['OptionId' => 3, 'OptionName' => 'Credit Card', 'OptionSequence' => 3],
            ['OptionId' => 4, 'OptionName' => 'Bank Draft', 'OptionSequence' => 4],
            ['OptionId' => 5, 'OptionName' => 'eGive', 'OptionSequence' => 5],
            ['OptionId' => 6, 'OptionName' => 'Check', 'OptionSequence' => 6],
            ['OptionId' => 7, 'OptionName' => 'Stock', 'OptionSequence' => 7],
            ['OptionId' => 8, 'OptionName' => 'Property', 'OptionSequence' => 8],
            ['OptionId' => 9, 'OptionName' => 'Cryptocurrency', 'OptionSequence' => 9],
            ['OptionId' => 10, 'OptionName' => 'Other', 'OptionSequence' => 10],
        ];
        
        return SlimUtils::renderJSON($response, $depositTypes);
    });
    
    // Convenience endpoint for person classifications
    $group->get('/person-classifications', function (Request $request, Response $response, array $args) use ($getListOptions): Response {
        $result = $getListOptions(1); // Person classifications list ID
        
        $statusCode = $result['status'] ?? 200;
        
        if (isset($result['error'])) {
            return SlimUtils::renderJSON($response->withStatus($statusCode), [
                'error' => $result['error']
            ]);
        }
        
        return SlimUtils::renderJSON($response, $result['data']);
    });
    
    // Convenience endpoint for family roles
    $group->get('/family-roles', function (Request $request, Response $response, array $args) use ($getListOptions): Response {
        $result = $getListOptions(2); // Family roles list ID
        
        $statusCode = $result['status'] ?? 200;
        
        if (isset($result['error'])) {
            return SlimUtils::renderJSON($response->withStatus($statusCode), [
                'error' => $result['error']
            ]);
        }
        
        return SlimUtils::renderJSON($response, $result['data']);
    });
    
    // Convenience endpoint for group types
    $group->get('/group-types', function (Request $request, Response $response, array $args) use ($getListOptions): Response {
        $result = $getListOptions(3); // Group types list ID
        
        $statusCode = $result['status'] ?? 200;
        
        if (isset($result['error'])) {
            return SlimUtils::renderJSON($response->withStatus($statusCode), [
                'error' => $result['error']
            ]);
        }
        
        return SlimUtils::renderJSON($response, $result['data']);
    });
});
