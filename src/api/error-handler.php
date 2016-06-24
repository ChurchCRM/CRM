<?php

$container['errorHandler'] = function ($container) {
  return function ($request, $response, $exception) use ($container) {
    $data = [
      'code' => $exception->getCode(),
      'message' => $exception->getMessage(),
      'file' => $exception->getFile(),
      'line' => $exception->getLine(),
      'trace' => explode("\n", $exception->getTraceAsString()),
    ];

    return $container->get('response')->withStatus(500)
      ->withHeader('Content-Type', 'application/json')
      ->write(json_encode($data));
  };
};

$container['notFoundHandler'] = function ($container) {
  return function ($request, $response) use ($container) {
    return $container['response']
      ->withStatus(404)
      ->withHeader('Content-Type', 'text/html')
      ->write("Can't find route for " . $request->getMethod() . " on " . $request->getUri());
  };
};

$container['notAllowedHandler'] = function ($container) {
  return function ($request, $response, $methods) use ($container) {
    return $container['response']
      ->withStatus(405)
      ->withHeader('Allow', implode(', ', $methods))
      ->withHeader('Content-type', 'text/html')
      ->write('Method must be one of: ' . implode(', ', $methods));
  };
};
