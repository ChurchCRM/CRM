<?php

// Routes

$app->post('/issues', function ($request, $response, $args) {
    $input = json_decode($request->getBody());

    return $this->SystemService->reportIssue($input);
});
