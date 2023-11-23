<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Backup\BackupDownloader;
use ChurchCRM\Backup\BackupJob;
use ChurchCRM\Backup\RestoreJob;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\FileSystemUtils;
use ChurchCRM\model\ChurchCRM\FamilyCustomQuery;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\NoteQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonCustomQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\PersonVolunteerOpportunityQuery;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/database', function (RouteCollectorProxy $group) {
    $group->delete('/reset', 'resetDatabase');
    $group->delete('/people/clear', 'clearPeopleTables');

    $group->get('/people/export/chmeetings', 'exportChMeetings');

    $group->post('/backup', function (Request $request, Response $response, array $args) {
        $input = (object) $request->getParsedBody();
        $BaseName = preg_replace('/[^a-zA-Z0-9\-_]/', '', SystemConfig::getValue('sChurchName')) . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));
        $BackupType = $input->BackupType;
        $Backup = new BackupJob(
            $BaseName,
            $BackupType,
            SystemConfig::getValue('bBackupExtraneousImages'),
            $input->EncryptBackup ?? '',
            $input->BackupPassword ?? ''
        );
        $Backup->execute();

        return SlimUtils::renderJSON($response, $Backup);
    });

    $group->post('/backupRemote', function (Request $request, Response $response, array $args) {
        if (SystemConfig::getValue('sExternalBackupUsername') && SystemConfig::getValue('sExternalBackupPassword') && SystemConfig::getValue('sExternalBackupEndpoint')) {
            $input = (object) $request->getParsedBody();
            $BaseName = preg_replace('/[^a-zA-Z0-9\-_]/', '', SystemConfig::getValue('sChurchName')) . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));
            $BackupType = $input->BackupType;
            $Backup = new BackupJob(
                $BaseName,
                $BackupType,
                SystemConfig::getValue('bBackupExtraneousImages'),
                $input->EncryptBackup ?? '',
                $input->BackupPassword ?? ''
            );
            $Backup->execute();
            $copyStatus = $Backup->copyToWebDAV(SystemConfig::getValue('sExternalBackupEndpoint'), SystemConfig::getValue('sExternalBackupUsername'), SystemConfig::getValue('sExternalBackupPassword'));

            return SlimUtils::renderJSON($response, $copyStatus);
        } else {
            throw new \Exception('WebDAV backups are not correctly configured.  Please ensure endpoint, username, and password are set', 500);
        }
    });

    $group->post('/restore', function (Request $request, Response $response, array $args) {
        $RestoreJob = new RestoreJob();
        $RestoreJob->execute();

        return SlimUtils::renderJSON($response, $RestoreJob);
    });

    $group->get('/download/{filename}', function (Request $request, Response $response, array $args) {
        $filename = $args['filename'];
        BackupDownloader::downloadBackup($filename);
    });
})->add(AdminRoleAuthMiddleware::class);

/**
 * A method that drops all db tables.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 */
function exportChMeetings(Request $request, Response $response, array $p_args)
{
    $header_data = [
        'First Name', 'Last Name', 'Middle Name', 'Gender',
        'Marital Status', 'Anniversary', 'Engagement Date',
        'Birthdate', 'Mobile Phone', 'Home Phone', 'Email',
        'Facebook', 'School', 'Grade', 'Employer', 'Job Title', 'Talents And Hobbies',
        'Address Line', 'Address Line 2', 'City', 'State', 'ZIP Code', 'Notes', 'Join Date',
        'Family Id', 'Family Role',
        'Baptism Date', 'Baptism Location', 'Nickname',
    ];
    $people = PersonQuery::create()->find();
    $list = [];
    foreach ($people as $person) {
        $family = $person->getFamily();
        $annaversery = ($family ? $family->getWeddingdate(SystemConfig::getValue('sDateFormatShort')) : '');
        $familyRole = $person->getFamilyRoleName();
        if ($familyRole == 'Head of Household') {
            $familyRole = 'Primary';
        }

        $chPerson = [$person->getFirstName(), $person->getLastName(), $person->getMiddleName(), $person->getGenderName(), '', $annaversery, '', $person->getFormattedBirthDate(), $person->getCellPhone(), $person->getHomePhone(), $person->getEmail(), $person->getFacebookID(), '', '', '', '', '', $person->getAddress1(), $person->getAddress2(), $person->getCity(), $person->getState(), $person->getZip(), '', $person->getMembershipDate(SystemConfig::getValue('sDateFormatShort')), $family ? $family->getId() : '', $familyRole, '', '', ''];
        array_push($list, $chPerson);
    }

    $stream = fopen('php://memory', 'w+');
    fputcsv($stream, $header_data);
    foreach ($list as $fields) {
        fputcsv($stream, $fields, ',');
    }

    rewind($stream);

    $response = $response->withHeader('Content-Type', 'text/csv');
    $response = $response->withHeader('Content-Disposition', 'attachment; filename="ChMeetings-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv"');

    return $response->withBody(new \Slim\Http\Stream($stream));
}

/**
 * A method that drops all db tables.
 *
 * @param \Slim\Http\Request  $p_request  The request.
 * @param \Slim\Http\Response $p_response The response.
 * @param array               $p_args     Arguments
 *
 * @return \Slim\Http\Response The augmented response.
 */
function resetDatabase(Request $request, Response $response, array $p_args)
{
    $connection = Propel::getConnection();
    $logger = LoggerUtils::getAppLogger();

    $logger->info('DB Drop started ');

    $statement = $connection->prepare('SHOW FULL TABLES;');
    $statement->execute();
    $dbTablesSQLs = $statement->fetchAll();

    foreach ($dbTablesSQLs as $dbTable) {
        if ($dbTable[1] == 'VIEW') {
            $alterSQL = 'DROP VIEW ' . $dbTable[0] . ' ;';
        } else {
            $alterSQL = 'DROP TABLE ' . $dbTable[0] . ' ;';
        }

        $dbAlterStatement = $connection->exec($alterSQL);
        $logger->debug('DB Update: ' . $alterSQL . ' done.');
    }

    AuthenticationManager::endSession();

    return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The database has been cleared.')]);
}

function clearPeopleTables(Request $request, Response $response, array $p_args)
{
    $connection = Propel::getConnection();
    $logger = LoggerUtils::getAppLogger();
    $curUserId = AuthenticationManager::getCurrentUser()->getId();
    $logger->info('People DB Clear started ');

    FamilyCustomQuery::create()->deleteAll($connection);
    $logger->info('Family custom deleted ');

    FamilyQuery::create()->deleteAll($connection);
    $logger->info('Families deleted');

    // Delete Family Photos
    FileSystemUtils::deleteFiles(SystemURLs::getImagesRoot() . '/Family/', Photo::getValidExtensions());
    FileSystemUtils::deleteFiles(SystemURLs::getImagesRoot() . '/Family/thumbnails/', Photo::getValidExtensions());
    $logger->info('family photos deleted');

    Person2group2roleP2g2rQuery::create()->deleteAll($connection);
    $logger->info('Person Group Roles deleted');

    PersonCustomQuery::create()->deleteAll($connection);
    $logger->info('Person Custom deleted');

    PersonVolunteerOpportunityQuery::create()->deleteAll($connection);
    $logger->info('Person Volunteer deleted');

    UserQuery::create()->filterByPersonId($curUserId, Criteria::NOT_EQUAL)->delete($connection);
    $logger->info('Users aide from person logged in deleted');

    PersonQuery::create()->filterById($curUserId, Criteria::NOT_EQUAL)->delete($connection);
    $logger->info('Persons aide from person logged in deleted');

    // Delete Person Photos
    FileSystemUtils::deleteFiles(SystemURLs::getImagesRoot() . '/Person/', Photo::getValidExtensions());
    FileSystemUtils::deleteFiles(SystemURLs::getImagesRoot() . '/Person/thumbnails/', Photo::getValidExtensions());

    $logger->info('people photos deleted');

    NoteQuery::create()->filterByPerId($curUserId, Criteria::NOT_EQUAL)->delete($connection);
    $logger->info('Notes deleted');

    return SlimUtils::renderJSON($response, ['success' => true, 'msg' => gettext('The people and families has been cleared from the database.')]);
}
