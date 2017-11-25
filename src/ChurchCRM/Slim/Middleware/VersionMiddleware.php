<?php

namespace ChurchCRM\Slim\Middleware;

use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\Service\SystemService;

class VersionMiddleware {

	public function __invoke( Request $request, Response $response, callable $next )
	{
		return $next( $request, $response )->withHeader( "CRM_VERSION", SystemService::getInstalledVersion());
	}
}