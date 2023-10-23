<?php

// Routes
$app->post('/issues', function ($request, $response, $args) use ($app) {
    $input = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);
    $systemService = $this->get('SystemService');

    return $systemService->reportIssue($input);
});
