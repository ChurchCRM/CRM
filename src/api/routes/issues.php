<?php
// Routes

$app->post('/issues', function ($request, $response, $args) {
  $input = (object)$request->getParsedBody();
  return $this->SystemService->reportIssue($input);
});
