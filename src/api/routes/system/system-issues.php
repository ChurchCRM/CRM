<?php

// Routes

$app->post('/issues', function ($request, $response, $args) {
    $input = json_decode($request->getBody(), null, 512, JSON_THROW_ON_ERROR);

    return $this->SystemService->reportIssue($input);
});
