<?php

require_once __DIR__ . '/../Include/LoadConfigs.php';

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\MvcAppFactory;
use ChurchCRM\Slim\Middleware\Request\Auth\ViewEventsRoleAuthMiddleware;

// Global gate: every /event/* request requires login (AuthMiddleware) AND
// the Events module to be enabled system-wide (ViewEventsRoleAuthMiddleware).
// Individual write routes (POST /event/editor, /event/types/*, etc.) add
// AddEventsRoleAuthMiddleware to additionally require the AddEvent permission.
$app = MvcAppFactory::create('/event', [
    'dashboardUrl' => '/event/dashboard',
    'dashboardText' => gettext('Back to Events Dashboard'),
    'roleMiddleware' => ViewEventsRoleAuthMiddleware::class,
]);

// Initialise the plugin system so hook listeners are registered for POST
// actions too. Header.php calls this during view rendering, but the
// action routes (e.g. POST /event/dashboard, which deletes an event) redirect
// without rendering a view — so without this, no plugin could observe
// Hooks::EVENT_DELETED from that path. init() is idempotent, so Header.php's
// later call on rendered pages is a no-op. Mirrors src/api/index.php.
PluginManager::init(SystemURLs::getDocumentRoot() . '/plugins');

// Register routes
require __DIR__ . '/routes/event.php';
require __DIR__ . '/routes/checkin.php';
require __DIR__ . '/routes/repeat-editor.php';
require __DIR__ . '/routes/calendar.php';
require __DIR__ . '/routes/list-events.php';
require __DIR__ . '/routes/types.php';
require __DIR__ . '/routes/editor.php';
require __DIR__ . '/routes/view.php';
require __DIR__ . '/routes/audit.php';

$app->run();
