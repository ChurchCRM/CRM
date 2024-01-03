<?php

namespace ChurchCRM\Slim\Request;

use ChurchCRM\dto\Photo;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteInterface;
use Slim\Routing\RouteContext;
use Slim\HttpCache\CacheProvider;

class SlimUtils
{
    public static function renderSuccessJSON(Response $response): Response
    {
        return self::renderJSON($response, ['status' => 'success']);
    }

    public static function renderStringJSON(Response $response, string $json, int $status = 200): Response
    {
        $response->getBody()->write($json);
        $response = $response->withHeader('Content-Type', 'application/json');
        if ($status !== 200) {
            $response = $response->withStatus($status);
        }
        return $response;
    }
    public static function renderJSON(Response $response, array $obj, int $status = 200): Response
    {
        return self::renderStringJson($response, json_encode($obj, JSON_THROW_ON_ERROR), $status);
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

    public static function getRouteArgument(Request $request, string $name): string
    {
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // return NotFound for non-existent route
        if (!$route instanceof RouteInterface) {
            throw new HttpNotFoundException($request);
        }

        return $route->getArgument($name);
    }

    public static function renderPhoto(Response $response, Photo $photo): Response
    {
        $cacheProvider = new CacheProvider();
        $response = $cacheProvider->withEtag($response, $photo->getPhotoURI());
        $response = $response->withHeader('Content-type', $photo->getPhotoContentType());

        $response->getBody()->write($photo->getPhotoBytes());

        return $response;
    }
}
