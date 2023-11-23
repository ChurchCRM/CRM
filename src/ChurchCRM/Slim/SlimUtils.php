<?php

namespace ChurchCRM\Slim\Request;

use ChurchCRM\dto\Photo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteContext;
use Slim\HttpCache\CacheProvider;
use Psr\Http\Message\StreamInterface;

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

    public static function getURIParamInt(Request $request, string $pramName): string {
        $val = SlimUtils::getURIParamString($request, $pramName);
        return intval($val);
    }

    public static function getURIParamString(Request $request, string $pramName): string {
        return $request->getQueryParams()[$pramName];
    }

    public static function getRouteArgument(Request $request, string $name): string {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // return NotFound for non-existent route
        if (empty($route)) {
            throw new HttpNotFoundException($request);
        }

        return $route->getArgument($name);
    }

    public static function renderPhoto(Response $response, Photo $photo): Response {

        /*$response = $response
            ->withBody($photo->getPhotoBytes())
            ->withHeader('Content-type', $photo->getPhotoContentType());*/

        $cacheProvider = new CacheProvider();
        return $cacheProvider->withEtag($response, $photo->getPhotoURI());

    }
}
