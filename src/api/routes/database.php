<?php

use Slim\Http\Request;
use Slim\Http\Response;
use Propel\Runtime\Propel;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Slim\Middleware\AdminRoleAuthMiddleware;


// Routes

$app->group('/database', function () {
    
    $this->post('/reset', 'resetDatabase');
    
    $this->post('/backup', function ($request, $response, $args) {
        $input = (object) $request->getParsedBody();
        $backup = $this->SystemService->getDatabaseBackup($input);
        echo json_encode($backup);
    });

    $this->post('/backupRemote', function() use ($app, $systemService) {
        $backup = $this->SystemService->copyBackupToExternalStorage();
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
})->add(new AdminRoleAuthMiddleware());


/**
 * A method that drops all db tables 
 *
 * @param \Slim\Http\Request $p_request The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array $p_args Arguments
 * @return \Slim\Http\Response The augmented response.
 */
function resetDatabase(Request $request, Response $response, array $p_args)
{
    $connection = Propel::getConnection();
    $logger = LoggerUtils::getAppLogger();

    $logger->info("DB Drop started ");

    $statement = $connection->prepare("SHOW FULL TABLES;");
    $statement->execute();
    $dbTablesSQLs = $statement->fetchAll();

    foreach ($dbTablesSQLs as $dbTable) {
        if ($dbTable[1] == "VIEW") {
            $alterSQL = "DROP VIEW " . $dbTable[0] . " ;";
        } else {
            $alterSQL = "DROP TABLE " . $dbTable[0] . " ;";
        }
    
        $dbAlterStatement = $connection->exec($alterSQL);
        $logger->info("DB Update: " . $alterSQL . " done.");
    }
    
  return $response->withJson(['success' => true, 'msg' => gettext('The database has been cleared.')]);
}