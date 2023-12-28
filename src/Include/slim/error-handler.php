<?php

use ChurchCRM\Utils\LoggerUtils;

error_reporting(E_ERROR);
ini_set('display_errors', true);
ini_set('log_errors', true);
ini_set('error_log', LoggerUtils::buildLogFilePath('slim'));

$errorMiddleware = $app->addErrorMiddleware(true, true, true, LoggerUtils::getSlimMVCLogger());
// Get the default error handler and register my custom error renderer.
$errorHandler = $errorMiddleware->getDefaultErrorHandler();

$container->set('errorHandler', fn ($container): \Closure => function ($request, $response, $exception) use ($container) {
    $data = [
        'code'    => $exception->getCode(),
        'message' => $exception->getMessage(),
        'file'    => $exception->getFile(),
        'line'    => $exception->getLine(),
        'trace'   => explode("\n", $exception->getTraceAsString()),
    ];

    return $container->get('response')->withStatus(500)
        ->withHeader('Content-Type', 'application/json')
        ->write(json_encode($data, JSON_THROW_ON_ERROR));
});

$container->set('notFoundHandler', fn ($container): \Closure => fn ($request, $response) => $container['response']
    ->withStatus(404)
    ->withHeader('Content-Type', 'text/html')
    ->write("Can't find route for " . $request->getMethod() . ' on ' . $request->getUri()));

$container->set('notAllowedHandler', fn ($container): \Closure => fn ($request, $response, $methods) => $container['response']
    ->withStatus(405)
    ->withHeader('Allow', implode(', ', $methods))
    ->withHeader('Content-type', 'text/html')
    ->write('Method must be one of: ' . implode(', ', $methods)));
