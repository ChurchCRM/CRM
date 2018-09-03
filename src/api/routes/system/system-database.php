<?php

use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FamilyCustomQuery;
use ChurchCRM\FamilyQuery;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\NoteQuery;
use ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\PersonCustomQuery;
use ChurchCRM\PersonQuery;
use ChurchCRM\PersonVolunteerOpportunityQuery;
use ChurchCRM\Service\SystemService;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\UserQuery;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Slim\Http\Request;
use Slim\Http\Response;
use ChurchCRM\BackupManager;

$app->group('/database', function () {

    $this->post('/reset', 'resetDatabase');
    $this->delete('/people/clear', 'clearPeopleTables');

    $this->get('/backup', function ($request, $response, $args) {

        return $response->withJSON(BackupManager::CaptureBackup(ChurchCRM\BackupType::FullBackup));
    });

    $this->post('/backupRemote', function ($request, $response, $args) {
        $backup = SystemService::copyBackupToExternalStorage();
        echo json_encode($backup);
    });

    $this->post('/restore', function ($request, $response, $args) {

        if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) &&
            empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
            $systemService = new SystemService();
            throw new \Exception(gettext('The selected file exceeds this servers maximum upload size of') . ": " . $systemService->getMaxUploadFileSize(), 500);
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

function clearPeopleTables(Request $request, Response $response, array $p_args)
{
    $connection = Propel::getConnection();
    $logger = LoggerUtils::getAppLogger();
    $curUserId = $_SESSION["user"]->getId();
    $logger->info("People DB Clear started ");


    FamilyCustomQuery::create()->deleteAll($connection);
    $logger->info("Family custom deleted ");

    FamilyQuery::create()->deleteAll($connection);
    $logger->info("Families deleted");

    // Delete Family Photos
    FileSystemUtils::deleteFiles(SystemURLs::getImagesRoot() . "/Family/", Photo::getValidExtensions());
    FileSystemUtils::deleteFiles(SystemURLs::getImagesRoot() . "/Family/thumbnails/", Photo::getValidExtensions());
    $logger->info("family photos deleted");

    Person2group2roleP2g2rQuery::create()->deleteAll($connection);
    $logger->info("Person Group Roles deleted");

    PersonCustomQuery::create()->deleteAll($connection);
    $logger->info("Person Custom deleted");

    PersonVolunteerOpportunityQuery::create()->deleteAll($connection);
    $logger->info("Person Volunteer deleted");

    UserQuery::create()->filterByPersonId($curUserId, Criteria::NOT_EQUAL)->delete($connection);
    $logger->info("Users aide from person logged in deleted");

    PersonQuery::create()->filterById($curUserId, Criteria::NOT_EQUAL)->delete($connection);
    $logger->info("Persons aide from person logged in deleted");

    // Delete Person Photos
    FileSystemUtils::deleteFiles(SystemURLs::getImagesRoot() . "/Person/", Photo::getValidExtensions());
    FileSystemUtils::deleteFiles(SystemURLs::getImagesRoot() . "/Person/thumbnails/", Photo::getValidExtensions());

    $logger->info("people photos deleted");

    NoteQuery::create()->filterByPerId($curUserId, Criteria::NOT_EQUAL)->delete($connection);
    $logger->info("Notes deleted");

    return $response->withJson(['success' => true, 'msg' => gettext('The people and families has been cleared from the database.')]);
}

