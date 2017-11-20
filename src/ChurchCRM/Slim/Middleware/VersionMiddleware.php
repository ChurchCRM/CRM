<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\SystemService;

class VersionMiddleware {

	public function __invoke( \Slim\Http\Request $request, \Slim\Http\Response $response, callable $next )
	{
        $systemService = new SystemService();
		return $next( $request, $response )->withHeader( "HEADER_VERSION", $systemService->getInstalledVersion() );
	}
}