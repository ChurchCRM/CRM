<?php

/**
 * MailChimp Plugin Routes.
 *
 * These routes are registered by the plugin system when the plugin is enabled.
 * The plugin system only loads routes for active plugins, providing system-wide security.
 * No middleware is needed to check if the plugin is enabled - if routes are loaded, the plugin is active.
 */

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Plugins\MailChimp\MailChimpPlugin;
use ChurchCRM\Slim\SlimUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Get plugin instance - guaranteed to be active since routes are only loaded for active plugins
$mailchimpPlugin = MailChimpPlugin::getInstance();

// Safety check - should never happen if plugin system is working correctly
if ($mailchimpPlugin === null) {
    return; // Don't register routes if plugin isn't loaded
}

// ============================================================================
// MVC Dashboard Route
// ============================================================================

$app->get('/mailchimp/dashboard', function (Request $request, Response $response) use ($mailchimpPlugin): Response {
    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('MailChimp Dashboard'),
        'isMailChimpActive' => $mailchimpPlugin->isActive(),
        'mailChimpLists' => $mailchimpPlugin->getLists(),
        'accountInfo' => $mailchimpPlugin->getAccountInfo(),
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
});

// ============================================================================
// MVC List Detail Routes
// ============================================================================

// People in CRM but not subscribed to MailChimp list
$app->get('/mailchimp/list/{listId}/unsubscribed', function (Request $request, Response $response, array $args) use ($mailchimpPlugin): Response {
    $list = $mailchimpPlugin->getList($args['listId']);
    
    if ($list === null) {
        throw new HttpNotFoundException($request, gettext('Invalid List id') . ': ' . $args['listId']);
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('People not in') . ' ' . $list['name'],
        'listId' => $list['id'],
    ];

    return $renderer->render($response, 'list-unsubscribed.php', $pageArgs);
});

// People in MailChimp list but not in CRM
$app->get('/mailchimp/list/{listId}/missing', function (Request $request, Response $response, array $args) use ($mailchimpPlugin): Response {
    $list = $mailchimpPlugin->getList($args['listId']);
    
    if ($list === null) {
        throw new HttpNotFoundException($request, gettext('Invalid List id') . ': ' . $args['listId']);
    }

    $renderer = new PhpRenderer(__DIR__ . '/../views/');
    $pageArgs = [
        'sRootPath' => SystemURLs::getRootPath(),
        'sPageTitle' => $list['name'] . ' ' . gettext('Audience not in the ChurchCRM'),
        'listId' => $list['id'],
    ];

    return $renderer->render($response, 'list-missing.php', $pageArgs);
});

// ============================================================================
// API Routes - All JSON endpoints are under /mailchimp/api/
// ============================================================================

$app->group('/mailchimp/api', function (RouteCollectorProxy $group) use ($mailchimpPlugin): void {
    // Get all lists
    $group->get('/lists', function (Request $request, Response $response) use ($mailchimpPlugin): Response {
        $lists = $mailchimpPlugin->getLists();
        
        return SlimUtils::renderJSON($response, ['success' => true, 'data' => $lists]);
    });

    // Get a specific list with members
    $group->get('/list/{id}', function (Request $request, Response $response, array $args) use ($mailchimpPlugin): Response {
        $list = $mailchimpPlugin->getList($args['id']);
        
        if ($list === null) {
            throw new HttpNotFoundException($request, gettext('List not found'));
        }

        return SlimUtils::renderJSON($response, ['success' => true, 'data' => ['list' => $list]]);
    });

    // Get emails in MailChimp list but not in CRM (for list maintenance)
    $group->get('/list/{id}/missing', function (Request $request, Response $response, array $args) use ($mailchimpPlugin): Response {
        $list = $mailchimpPlugin->getList($args['id']);
        
        if ($list === null) {
            throw new HttpNotFoundException($request, gettext('List not found'));
        }

        $mailchimpListMembers = $list['members'] ?? [];
        $peopleWithEmails = PersonQuery::create()
            ->filterByEmail(null, Criteria::NOT_EQUAL)
            ->_or()
            ->filterByWorkEmail(null, Criteria::NOT_EQUAL)
            ->find();

        foreach ($peopleWithEmails as $person) {
            $mailchimpListMembers = array_filter($mailchimpListMembers, function ($member) use ($person) {
                $email = strtolower($member['email']);
                return $email !== strtolower($person->getEmail() ?? '') 
                    && $email !== strtolower($person->getWorkEmail() ?? '');
            });
        }

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'data' => [
                'id' => $list['id'],
                'name' => $list['name'],
                'members' => array_values($mailchimpListMembers),
            ],
        ]);
    });

    // Get CRM people not subscribed to a list
    $group->get('/list/{id}/not-subscribed', function (Request $request, Response $response, array $args) use ($mailchimpPlugin): Response {
        $list = $mailchimpPlugin->getList($args['id']);
        
        if ($list === null) {
            throw new HttpNotFoundException($request, gettext('List not found'));
        }

        $mailchimpListMembers = $list['members'] ?? [];
        $mailchimpEmails = array_map('strtolower', array_column($mailchimpListMembers, 'email'));
        
        $personsNotInMailchimp = [];
        $peopleWithEmails = PersonQuery::create()
            ->filterByEmail(null, Criteria::NOT_EQUAL)
            ->_or()
            ->filterByWorkEmail(null, Criteria::NOT_EQUAL)
            ->find();

        foreach ($peopleWithEmails as $person) {
            $inList = false;
            
            if (!empty($person->getEmail()) && in_array(strtolower($person->getEmail()), $mailchimpEmails)) {
                $inList = true;
            }
            
            if (!$inList && !empty($person->getWorkEmail()) && in_array(strtolower($person->getWorkEmail()), $mailchimpEmails)) {
                $inList = true;
            }

            if (!$inList) {
                $emails = [];
                if (!empty($person->getEmail())) {
                    $emails[] = $person->getEmail();
                }
                if (!empty($person->getWorkEmail())) {
                    $emails[] = $person->getWorkEmail();
                }
                $personsNotInMailchimp[] = [
                    'id' => $person->getId(),
                    'name' => $person->getFullName(),
                    'emails' => $emails,
                ];
            }
        }

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'data' => [
                'id' => $list['id'],
                'name' => $list['name'],
                'members' => $personsNotInMailchimp,
            ],
        ]);
    });

    // Get person's MailChimp subscription status
    $group->get('/person/{personId}', function (Request $request, Response $response, array $args) use ($mailchimpPlugin): Response {
        $person = PersonQuery::create()->findPk((int) $args['personId']);
        
        if ($person === null) {
            throw new HttpNotFoundException($request, gettext('Person not found'));
        }

        $emailToLists = [];
        if (!empty($person->getEmail())) {
            $emailToLists[] = [
                'email' => $person->getEmail(),
                'emailMD5' => md5(strtolower($person->getEmail())),
                'list' => $mailchimpPlugin->isEmailInMailChimp($person->getEmail()),
            ];
        }
        if (!empty($person->getWorkEmail())) {
            $emailToLists[] = [
                'email' => $person->getWorkEmail(),
                'emailMD5' => md5(strtolower($person->getWorkEmail())),
                'list' => $mailchimpPlugin->isEmailInMailChimp($person->getWorkEmail()),
            ];
        }

        return SlimUtils::renderJSON($response, $emailToLists);
    });

    // Get family's MailChimp subscription status
    $group->get('/family/{familyId}', function (Request $request, Response $response, array $args) use ($mailchimpPlugin): Response {
        $family = FamilyQuery::create()->findPk((int) $args['familyId']);
        
        if ($family === null) {
            throw new HttpNotFoundException($request, gettext('Family not found'));
        }

        $emailToLists = [];
        if (!empty($family->getEmail())) {
            $emailToLists[] = [
                'email' => $family->getEmail(),
                'emailMD5' => md5(strtolower($family->getEmail())),
                'list' => $mailchimpPlugin->isEmailInMailChimp($family->getEmail()),
            ];
        }

        return SlimUtils::renderJSON($response, $emailToLists);
    });
});
