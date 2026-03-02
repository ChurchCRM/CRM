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
use ChurchCRM\Utils\FileSystemUtils;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\PersonQuery;

$app->group('/api/database', function (RouteCollectorProxy $group): void {

    /**
     * @OA\Get(
     *     path="/api/database/people/export/chmeetings",
     *     summary="Export all people as a ChMeetings-compatible CSV file (Admin role required)",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="CSV file download with person data in ChMeetings format")
     * )
     */
    $group->get('/people/export/chmeetings', 'exportChMeetings');

    /**
     * @OA\Delete(
     *     path="/api/database/reset",
     *     summary="Drop all database tables and views, clear uploaded images, and destroy the session (Admin role required)",
     *     description="This operation is irreversible. After reset the session is destroyed and default credentials (admin/changeme) apply.",
     *     tags={"Admin"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Database reset completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="msg", type="string"),
     *             @OA\Property(property="dropped", type="integer"),
     *             @OA\Property(property="defaultUsername", type="string"),
     *             @OA\Property(property="defaultPassword", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Admin role required"),
     *     @OA\Response(response=500, description="Database reset failed")
     * )
     */
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

            // Remove uploaded images for people and families from Images root
            try {
                $imagesRoot = SystemURLs::getImagesRoot();
                $personDir = $imagesRoot . '/person';
                $familyDir = $imagesRoot . '/family';

                // Remove and recreate person dir
                if (is_dir($personDir)) {
                    FileSystemUtils::recursiveRemoveDirectory($personDir);
                }
                @mkdir($personDir, 0755, true);

                // Remove and recreate family dir
                if (is_dir($familyDir)) {
                    FileSystemUtils::recursiveRemoveDirectory($familyDir);
                }
                @mkdir($familyDir, 0755, true);

                $logger->info('Database reset: cleared person and family Images directories', ['personDir' => $personDir, 'familyDir' => $familyDir]);
            } catch (\Throwable $e) {
                $logger->warning('Failed to clear Images directories during DB reset', ['error' => $e->getMessage()]);
            }

            // Destroy the session and clear the session cookie
            // This ensures the client doesn't send stale session cookies on subsequent requests
            $sessionName = session_name();
            $cookieParams = session_get_cookie_params();
            session_destroy();

            // Build Set-Cookie header to expire the session cookie
            $expiredCookie = sprintf(
                '%s=; expires=Thu, 01 Jan 1970 00:00:00 GMT; Max-Age=0; path=%s%s%s',
                $sessionName,
                $cookieParams['path'] ?: '/',
                $cookieParams['domain'] ? '; domain=' . $cookieParams['domain'] : '',
                $cookieParams['secure'] ? '; secure' : ''
            );

            return SlimUtils::renderJSON(
                $response->withHeader('Set-Cookie', $expiredCookie),
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
