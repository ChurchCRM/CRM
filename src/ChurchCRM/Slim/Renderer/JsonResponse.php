<?php

namespace ChurchCRM\Slim\Request;

use Psr\Http\Message\ResponseInterface as Response;

class JsonResponse
{
    public static function render(Response $response, array $obj)
    {
        $response->getBody()->write(json_encode($obj));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function renderSuccess(Response $response)
    {
        return self::render(['status' => 'success']);
    }
}
