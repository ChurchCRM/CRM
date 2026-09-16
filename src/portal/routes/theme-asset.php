<?php

use ChurchCRM\Portal\ThemeAssetStreamer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// GET /portal/theme/{name}/{path} — stream one file out of a theme folder.
//
// Everything that is not an allow-listed extension inside a real theme folder
// is a 404: no templates, no theme.json, no PHP, no traversal. See
// ThemeAssetStreamer and design §2.2 step 6 / P5.
$app->get('/theme/{name}/{path:.*}', function (Request $request, Response $response, array $args): Response {
    $themeName = (string) ($args['name'] ?? '');
    $path = (string) ($args['path'] ?? '');

    $absolutePath = ThemeAssetStreamer::resolve($themeName, $path);
    if ($absolutePath === null) {
        // A plain 404, not the module's HTML error page: this route is public
        // and serves static files, so there is no session to render a page for.
        $response->getBody()->write('Not found');

        return $response->withStatus(404)->withHeader('Content-Type', 'text/plain');
    }

    return ThemeAssetStreamer::stream($request, $response, $themeName, $path, $absolutePath);
});
