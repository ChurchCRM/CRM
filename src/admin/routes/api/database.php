<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CsvExporter;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\PersonQuery;

$app->group('/api/database', function (RouteCollectorProxy $group): void {

    $group->get('/people/export/chmeetings', 'exportChMeetings');

    /**
     * Reset database - drops all tables and views
     * This operation is irreversible and ends the current session
     */
    $group->delete('/reset', function (Request $request, Response $response): Response {
        $connection = Propel::getConnection();
        $logger = LoggerUtils::getAppLogger();

        $logger->info('Database reset started');

        try {
            // Disable foreign key checks to avoid constraint violations during drop
            $connection->exec('SET FOREIGN_KEY_CHECKS = 0;');
            
            // Get all tables and views
            $statement = $connection->prepare('SHOW FULL TABLES;');
            $statement->execute();
            $dbObjects = $statement->fetchAll();

            $droppedCount = 0;
            foreach ($dbObjects as $dbObject) {
                $objectName = $dbObject[0];
                $objectType = $dbObject[1];
                
                try {
                    if ($objectType === 'VIEW') {
                        $dropSQL = "DROP VIEW `$objectName`;";
                    } else {
                        $dropSQL = "DROP TABLE `$objectName`;";
                    }
                    
                    $connection->exec($dropSQL);
                    $droppedCount++;
                    $logger->debug("Dropped $objectType: $objectName");
                } catch (\PDOException $e) {
                    $logger->warning("Failed to drop $objectType $objectName: " . $e->getMessage());
                }
            }

            // Re-enable foreign key checks
            $connection->exec('SET FOREIGN_KEY_CHECKS = 1;');

            $logger->info("Database reset completed - dropped $droppedCount objects (tables and views)");

            // Destroy the session directly
            session_destroy();

            return SlimUtils::renderJSON(
                $response,
                [
                    'success' => true,
                    'msg' => gettext('The database has been cleared.'),
                    'dropped' => $droppedCount,
                    // Provide default credentials for post-reset login (matches other UI flows)
                    'defaultUsername' => 'admin',
                    'defaultPassword' => 'changeme'
                ]
            );
        } catch (\Throwable $e) {
            return SlimUtils::renderErrorJSON($response, gettext('Database reset failed'), [], 500, $e, $request);
        }
    });

});

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

    // Use CsvExporter for RFC 4180 compliance and formula injection prevention
    CsvExporter::create(
        $header_data,
        $list,
        'ChMeetings',
        'UTF-8',
        true
    );

    return $response;
}
