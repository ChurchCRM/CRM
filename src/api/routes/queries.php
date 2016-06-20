<?php
// Routes


$app->group('/queries', function () use ($app) {
  $reportingService = $app->ReportingService;

  $app->get("/", function () use ($app, $reportingService) {
    echo $reportingService->getQueriesJSON($reportingService->getQuery());
  });

  $app->get("/:id", function ($id) use ($app, $reportingService) {
    echo $reportingService->getQueriesJSON($reportingService->getQuery($id));
  });

  $app->get("/:id/details", function ($id) use ($app, $reportingService) {
    echo json_encode(["Query" => $reportingService->getQuery($id), "Parameters" => $reportingService->getQueryParameters($id)]);
  });

  $app->post('/:id', function () use ($app, $reportingService) {
    $input = getJSONFromApp($app);
    echo json_encode($reportingService->queryDatabase($input));
  });
});
