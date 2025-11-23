<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Backup\BackupDownloader;
use ChurchCRM\Backup\BackupJob;
use ChurchCRM\Backup\RestoreJob;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Middleware\Request\Auth\AdminRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/database', function (RouteCollectorProxy $group): void {

    $group->get('/people/export/chmeetings', 'exportChMeetings');

    $group->post('/backup', function (Request $request, Response $response, array $args): Response {
        $input = $request->getParsedBody();
        $BaseName = preg_replace('/[^a-zA-Z0-9\-_]/', '', SystemConfig::getValue('sChurchName')) . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));
        $BackupType = $input['BackupType'];
        $Backup = new BackupJob(
            $BaseName,
            $BackupType,
            $input['EncryptBackup'] ?? '',
            $input['BackupPassword'] ?? ''
        );
        $Backup->execute();

        return SlimUtils::renderJSON(
            $response,
            json_decode(
                json_encode(
                    $Backup,
                    JSON_THROW_ON_ERROR
                ),
                (bool) JSON_OBJECT_AS_ARRAY,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    });

    $group->post('/backupRemote', function (Request $request, Response $response, array $args): Response {
        if (SystemConfig::getValue('sExternalBackupUsername') && SystemConfig::getValue('sExternalBackupPassword') && SystemConfig::getValue('sExternalBackupEndpoint')) {
            $input = $request->getParsedBody();
            $BaseName = preg_replace(
                '/[^a-zA-Z0-9\-_]/',
                '',
                SystemConfig::getValue('sChurchName')
            ) . '-' . date(SystemConfig::getValue('sDateFilenameFormat'));
            $BackupType = $input['BackupType'];
            $Backup = new BackupJob(
                $BaseName,
                $BackupType,
                $input['EncryptBackup'] ?? '',
                $input['BackupPassword'] ?? ''
            );
            $Backup->execute();
            $copyStatus = $Backup->copyToWebDAV(
                SystemConfig::getValue('sExternalBackupEndpoint'),
                SystemConfig::getValue('sExternalBackupUsername'),
                SystemConfig::getValue('sExternalBackupPassword')
            );

            return SlimUtils::renderJSON($response, ['copyStatus' => $copyStatus]);
        } else {
            throw new \Exception('WebDAV backups are not correctly configured.  Please ensure endpoint, username, and password are set', 500);
        }
    });

    $group->post('/restore', function (Request $request, Response $response, array $args): Response {
        $RestoreJob = new RestoreJob();
        $RestoreJob->execute();

        return SlimUtils::renderJSON(
            $response,
            json_decode(
                json_encode(
                    $RestoreJob,
                    JSON_THROW_ON_ERROR
                ),
                (bool) JSON_OBJECT_AS_ARRAY,
                512,
                JSON_THROW_ON_ERROR
            )
        );
    });

    $group->get('/download/{filename}', function (Request $request, Response $response, array $args): Response {
        $filename = $args['filename'];
        BackupDownloader::downloadBackup($filename);

        return $response;
    });
})->add(AdminRoleAuthMiddleware::class);

function exportChMeetings(Request $request, Response $response, array $args): Response
{
    $header_data = [
        'First Name',
        'Last Name',
        'Middle Name',
        'Gender',
        'Marital Status',
        'Anniversary',
        'Engagement Date',
        'Birthdate',
        'Mobile Phone',
        'Home Phone',
        'Email',
        'Facebook',
        'School',
        'Grade',
        'Employer',
        'Job Title',
        'Talents And Hobbies',
        'Address Line',
        'Address Line 2',
        'City',
        'State',
        'ZIP Code',
        'Notes',
        'Join Date',
        'Family Id',
        'Family Role',
        'Baptism Date',
        'Baptism Location',
        'Nickname',
    ];
    $people = PersonQuery::create()->find();
    $list = [];
    foreach ($people as $person) {
        $family = $person->getFamily();
        $anniversary = ($family ? $family->getWeddingdate(SystemConfig::getValue('sDateFormatLong')) : '');
        $familyRole = $person->getFamilyRoleName();
        if ($familyRole === 'Head of Household') {
            $familyRole = 'Primary';
        }

        $chPerson = [
            $person->getFirstName(),
            $person->getLastName(),
            $person->getMiddleName(),
            $person->getGenderName(),
            '',
            $anniversary,
            '',
            $person->getFormattedBirthDate(),
            $person->getCellPhone(),
            $person->getHomePhone(),
            $person->getEmail(),
            $person->getFacebook(),
            '',
            '',
            '',
            '',
            '',
            $person->getAddress1(),
            $person->getAddress2(),
            $person->getCity(),
            $person->getState(),
            $person->getZip(),
            '',
            $person->getMembershipDate(SystemConfig::getValue('sDateFormatLong')),
            $family ? $family->getId() : '',
            $familyRole,
            '',
            '',
            ''
        ];
        $list[] = $chPerson;
    }

    $out = fopen('php://temp', 'w+');
    fputcsv($out, $header_data);
    foreach ($list as $fields) {
        fputcsv($out, $fields, ',');
    }
    rewind($out);
    $csvData = stream_get_contents($out);
    fclose($out);

    $response = $response->withHeader('Content-Type', 'text/csv');
    $response = $response->withHeader(
        'Content-Disposition',
        'attachment; filename="ChMeetings-' . date(SystemConfig::getValue('sDateFilenameFormat')) . '.csv"'
    );
    $response->getBody()->write($csvData);

    return $response;
}
