<?php

namespace ChurchCRM\Slim\Request;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SlimUtils
{
    public static function renderSuccessJSON(Response $response): Response
    {
        return self::renderJSON($response, ['status' => 'success']);
    }

    public static function renderJSON(Response $response, array $obj, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($obj));
        $response = $response->withHeader('Content-Type', 'application/json');
        if ($status !== 200) {
            $response = $response->withStatus($status);
        }
        return $response;
    }

    public static function renderRedirect(Response $response, string $url): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }

    public static function getURIParamInt(Request $request, string $pramName): string
    {
        $val = SlimUtils::getURIParamString($request, $pramName);
        return intval($val);
    }

    public static function getURIParamString(Request $request, string $pramName): string
    {
        return $request->getQueryParams()[$pramName];
    }
}