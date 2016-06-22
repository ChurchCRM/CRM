<?php
// Routes

$app->post('/issues', function ($request, $response, $args) {
  $input = $request->getParsedBody();
  return $response->withJson($this->SystemService->reportIssue($input));
});
