<?php

use ChurchCRM\Service\SystemService;

// Routes

$app->group('/database', function () {
    $this->post('/backup', function ($request, $response, $args) {
        $input = (object) $request->getParsedBody();
        $backup = $this->SystemService->getDatabaseBackup($input);
        echo json_encode($backup);
    });

    $this->post('/restore', function ($request, $response, $args) {
      
      if ( $_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) &&
            empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0 )
        {  
          $systemService = new SystemService();
          throw new \Exception(gettext('The selected file exceeds this servers maximum upload size of').": ". $systemService->getMaxUploadFileSize()  , 500);
        }
        $fileName = $_FILES['restoreFile'];
        $restore = $this->SystemService->restoreDatabaseFromBackup($fileName);
        echo json_encode($restore);
    });

    $this->get('/download/{filename}', function ($request, $response, $args) {
        $filename = $args['filename'];
        $this->SystemService->download($filename);
    });
});
