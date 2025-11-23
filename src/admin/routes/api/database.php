<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\Propel;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/api/database', function (RouteCollectorProxy $group): void {

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
        } catch (\Exception $e) {
            $logger->error('Database reset failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return SlimUtils::renderJSON(
                $response,
                [
                    'success' => false,
                    'msg' => gettext('Database reset failed: ') . $e->getMessage()
                ],
                500
            );
        }
    });

});
