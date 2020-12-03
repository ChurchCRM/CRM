<?php

namespace ChurchCRM\Slim\Middleware;

use ChurchCRM\Service\SystemService;
use Slim\Http\Request;
use Slim\Http\Response;

class VersionMiddleware {

	public function __invoke( Request $request, Response $response, callable $next )
	{
		return $next( $request, $response )->withHeader( "CRM_VERSION", SystemService::getInstalledVersion());
	}
}
