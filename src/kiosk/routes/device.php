<?php

use ChurchCRM\dto\Notification;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\KioskAssignment;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\LoggerUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

// Device routes - these are accessed by kiosk devices themselves (not admins)
// They use kiosk cookie authentication, not user authentication

/**
 * Validate kiosk device is found, accepted by admin, and has an active assignment
 * with a linked event. Returns [kiosk, assignment, event] on success or a
 * Response on failure.
 *
 * @return array{0: \ChurchCRM\model\ChurchCRM\KioskDevice, 1: \ChurchCRM\model\ChurchCRM\KioskAssignment, 2: \ChurchCRM\model\ChurchCRM\Event}|Response
 */
function requireAcceptedKioskWithEvent(callable $getKiosk, Response $response): array|Response
{
    $kiosk = $getKiosk();
    if ($kiosk === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Kiosk device not found'), [], 401);
    }
    if (!$kiosk->getAccepted()) {
        return SlimUtils::renderErrorJSON($response, gettext('Kiosk device has not been accepted by an administrator'), [], 403);
    }
    $assignment = $kiosk->getActiveAssignment();
    if ($assignment === null) {
        return SlimUtils::renderErrorJSON($response, gettext('No active event assignment'), [], 403);
    }
    $event = $assignment->getEvent();
    if ($event === null) {
        return SlimUtils::renderErrorJSON($response, gettext('Assigned event not found'), [], 404);
    }

    return [$kiosk, $assignment, $event];
}

/**
 * Extract the IDs of all people in the active class roster for a given kiosk
 * assignment. getActiveGroupMembers() returns a Propel ObjectCollection of
 * Person objects, so we must iterate and call getId() — array_column() does
 * not work with Propel ORM objects.
 *
 * @return int[]
 */
function getActiveRosterIds(KioskAssignment $assignment): array
{
    $rosterIds = [];
    foreach ($assignment->getActiveGroupMembers() as $member) {
        $rosterIds[] = $member->getId();
    }
    return $rosterIds;
}

$app->group('/device', function (RouteCollectorProxy $group) use ($getKioskFromCookie): void {
    $group->get('', function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../templates/kioskDevices/');
        $pageObjects = ['sRootPath' => $_SESSION['sRootPath']];

        return $renderer->render($response, 'sunday-school-class-view.php', $pageObjects);
    });

    $group->get('/', function (Request $request, Response $response) {
        $renderer = new PhpRenderer(__DIR__ . '/../templates/kioskDevices/');
        $pageObjects = ['sRootPath' => $_SESSION['sRootPath']];

        return $renderer->render($response, 'sunday-school-class-view.php', $pageObjects);
    });

    // Heartbeat: only requires kiosk to exist (not accepted) so pending
    // devices can poll for acceptance status
    $group->get('/heartbeat', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $kiosk = $getKioskFromCookie();
        if ($kiosk === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Kiosk device not found'), [], 401);
        }

        return SlimUtils::renderJSON($response, $kiosk->heartbeat());
    });

    $group->post('/checkin', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $input = $request->getParsedBody();
        $personId = InputUtils::filterInt($input['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }

        $checkedInById = InputUtils::filterInt($input['CheckedInById'] ?? 0) ?: null;

        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, , $event] = $result;

        $status = $event->checkInPerson($personId, $checkedInById);

        return SlimUtils::renderJSON($response, $status);
    });

    $group->post('/checkout', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $input = $request->getParsedBody();
        $personId = InputUtils::filterInt($input['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }

        $checkedOutById = InputUtils::filterInt($input['CheckedOutById'] ?? 0) ?: null;

        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, , $event] = $result;

        $status = $event->checkOutPerson($personId, $checkedOutById);

        return SlimUtils::renderJSON($response, $status);
    });

    $group->post('/triggerNotification', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $input = $request->getParsedBody();
        $personId = InputUtils::filterInt($input['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }

        $Person = PersonQuery::create()
                ->findOneById($personId);

        if ($Person === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Person not found'), [], 404);
        }

        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, , $event] = $result;
        
        // Get event/group name for the notification
        $groups = $event->getGroups();
        $eventName = $groups->count() > 0 ? $groups->getFirst()->getName() : $event->getTitle();

        $family = $Person->getFamily();
        if ($family === null) {
            LoggerUtils::getAppLogger()->warning('triggerNotification: Person has no family', [
                'personId' => $personId,
                'personName' => $Person->getFirstName() . ' ' . $Person->getLastName(),
                'eventName' => $eventName,
            ]);
            return SlimUtils::renderErrorJSON($response, gettext('Person has no family'), [], 404);
        }

        LoggerUtils::getAppLogger()->info('triggerNotification: Sending notification', [
            'personId' => $personId,
            'personName' => $Person->getFirstName() . ' ' . $Person->getLastName(),
            'eventName' => $eventName,
            'familyId' => $family->getId(),
            'recipientCount' => count($family->getAdults()),
        ]);

        $Notification = new Notification();
        $Notification->setPerson($Person);
        $Notification->setRecipients($family->getAdults());
        $Notification->setEventName($eventName);
        $Notification->setProjectorText($event->getType() . '-' . $Person->getId());
        $status = $Notification->send();

        // Log notification result — send() returns ['status' => '', 'methods' => ['email: 1', 'sms: 1', ...]]
        // A method is successful when its string ends with ': 1' (PHP bool true cast to string)
        $methods = $status['methods'] ?? [];
        $anySuccess = !empty(array_filter($methods, fn ($m) => str_ends_with((string) $m, '1')));
        $logContext = [
            'personId' => $personId,
            'personName' => $Person->getFirstName() . ' ' . $Person->getLastName(),
            'eventName' => $eventName,
            'methods' => $methods,
        ];
        if ($anySuccess) {
            LoggerUtils::getAppLogger()->info('triggerNotification: Notification sent', $logContext);
        } else {
            LoggerUtils::getAppLogger()->warning('triggerNotification: No notification channel delivered', $logContext);
        }

        return SlimUtils::renderJSON($response, $status);
    });

    $group->get('/activeClassMembers', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, $assignment, $event] = $result;

        $members = $assignment->getActiveGroupMembers();
        $groups = $event->getGroups();
        $groupName = $groups->count() > 0 ? $groups->getFirst()->getName() : '';

        // Build response array using Person object methods
        // Use configured timezone for"today" calculations
        $today = DateTimeUtils::getToday();
        $currentMonth = (int) $today->format('n');
        $currentDay = (int) $today->format('j');
        $currentYear = (int) $today->format('Y');
        $peopleData = [];
        foreach ($members as $person) {
            $photo = new Photo('Person', $person->getId());

            // Get birth data - returns 0 when not set
            $birthMonth = (int) $person->getBirthMonth();
            $birthDay = (int) $person->getBirthDay();
            $birthYear = $person->getBirthYear();

            // Calculate age - try multiple approaches
            $age = null;
            if (!empty($birthYear) && $birthMonth > 0 && $birthDay > 0) {
                // Calculate age manually to avoid hideAge() interference
                $birthDate = DateTimeUtils::createDateTime("$birthYear-$birthMonth-$birthDay");
                $ageInterval = $today->diff($birthDate);
                $age = $ageInterval->y;
            }

            // Birthday is"this month" if birthMonth matches current month
            $birthdayThisMonth = ($birthMonth > 0 && $birthMonth === $currentMonth);

            // Calculate if birthday is upcoming (within next 14 days) or recent (within past 14 days)
            $birthdayUpcoming = false;
            $birthdayRecent = false;
            $birthdayToday = false;
            if ($birthMonth > 0 && $birthDay > 0) {
                // Calculate this year's birthday using configured timezone
                $thisBirthday = DateTimeUtils::getToday();
                $thisBirthday->setDate($currentYear, $birthMonth, $birthDay);

                // Get the difference in days
                $interval = $today->diff($thisBirthday);
                $daysDiff = (int) $interval->format('%r%a');

                // Check if birthday is today
                if ($daysDiff === 0) {
                    $birthdayToday = true;
                    $birthdayUpcoming = true;
                }
                // Upcoming: birthday in next 14 days (positive diff)
                elseif ($daysDiff > 0 && $daysDiff <= 14) {
                    $birthdayUpcoming = true;
                }
                // Recent: birthday in past 14 days (negative diff)
                elseif ($daysDiff < 0 && $daysDiff >= -14) {
                    $birthdayRecent = true;
                }
            }

            $family = $person->getFamily();
            $familyId = $family !== null ? $family->getId() : null;

            $peopleData[] = [
                'Id' => $person->getId(),
                'FirstName' => $person->getFirstName(),
                'LastName' => $person->getLastName(),
                'Gender' => $person->getGender(),
                'age' => $age,
                'birthdayThisMonth' => $birthdayThisMonth,
                'birthdayUpcoming' => $birthdayUpcoming,
                'birthdayRecent' => $birthdayRecent,
                'birthdayToday' => $birthdayToday,
                'birthDay' => $birthDay > 0 ? $birthDay : null,
                'birthMonth' => $birthMonth > 0 ? $birthMonth : null,
                'hasPhoto' => $photo->hasUploadedPhoto(),
                'RoleName' => $person->getVirtualColumn('RoleName'),
                'status' => $person->getVirtualColumn('status'),
                'familyId' => $familyId,
            ];
        }

        // Check if any notification method is configured
        $openLpPlugin = PluginManager::getPlugin('openlp');
        $openLpEnabled = $openLpPlugin !== null && $openLpPlugin->isEnabled() && $openLpPlugin->isConfigured();

        $vonagePlugin = PluginManager::getPlugin('vonage');
        $smsEnabled = $vonagePlugin !== null && $vonagePlugin->isEnabled() && $vonagePlugin->isConfigured();

        $notificationsEnabled = SystemConfig::isEmailEnabled() ||
                                $smsEnabled ||
                                $openLpEnabled;

        return SlimUtils::renderJSON($response, [
            'People' => $peopleData,
            'GroupName' => $groupName,
            'notificationsEnabled' => $notificationsEnabled,
        ]);
    });

    $group->get('/activeClassMember/{PersonId}/photo', function (Request $request, Response $response, array $args) use ($getKioskFromCookie): Response {
        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, $assignment] = $result;

        $personId = InputUtils::filterInt($args['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }

        // Verify the person is in the active class roster.
        // Note: getActiveGroupMembers() returns a Propel ObjectCollection of Person objects,
        // so we iterate and call getId() rather than using array_column().
        $rosterIds = getActiveRosterIds($assignment);
        if (!in_array($personId, $rosterIds, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Person not in active class roster'), [], 403);
        }

        $photo = new Photo('Person', $personId);
        if (!$photo->hasUploadedPhoto()) {
            return SlimUtils::renderErrorJSON($response, gettext('No photo found for this person'), [], 404);
        }

        return SlimUtils::renderPhoto($response, $photo);
    });

    $group->get('/activeClassMember/{PersonId}/family', function (Request $request, Response $response, array $args) use ($getKioskFromCookie): Response {
        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, $assignment] = $result;

        $personId = InputUtils::filterInt($args['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }

        // Verify the requested person is actually part of the kiosk's
        // active class roster — prevents enumeration outside the assigned
        // group's members.
        // Note: getActiveGroupMembers() returns a Propel ObjectCollection of Person objects,
        // so we iterate and call getId() rather than using array_column().
        $rosterIds = getActiveRosterIds($assignment);
        if (!in_array($personId, $rosterIds, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Person not in active class roster'), [], 403);
        }

        $person = PersonQuery::create()->findOneById($personId);
        if ($person === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Person not found'), [], 404);
        }

        $family = $person->getFamily();
        if ($family === null) {
            return SlimUtils::renderJSON($response, ['members' => []]);
        }

        $adults = $family->getAdults();
        $membersData = [];
        foreach ($adults as $adult) {
            if ($adult->getId() === $personId) {
                continue; // Exclude the person themselves
            }
            $photo = new Photo('Person', $adult->getId());
            $membersData[] = [
                'Id'        => $adult->getId(),
                'FirstName' => $adult->getFirstName(),
                'LastName'  => $adult->getLastName(),
                'hasPhoto'  => $photo->hasUploadedPhoto(),
            ];
        }

        return SlimUtils::renderJSON($response, ['members' => $membersData]);
    });

    $group->get('/activeClassMember/{PersonId}/familyMember/{FamilyMemberId}/photo', function (Request $request, Response $response, array $args) use ($getKioskFromCookie): Response {
        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, $assignment] = $result;

        $personId = InputUtils::filterInt($args['PersonId'] ?? 0);
        if ($personId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid person ID'), [], 400);
        }

        $familyMemberId = InputUtils::filterInt($args['FamilyMemberId'] ?? 0);
        if ($familyMemberId <= 0) {
            return SlimUtils::renderErrorJSON($response, gettext('Invalid family member ID'), [], 400);
        }

        // Verify the class member (personId) is in the active class roster
        $rosterIds = getActiveRosterIds($assignment);
        if (!in_array($personId, $rosterIds, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Person not in active class roster'), [], 403);
        }

        // Verify the family member is an adult in the class member's family
        $person = PersonQuery::create()->findOneById($personId);
        if ($person === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Person not found'), [], 404);
        }

        $family = $person->getFamily();
        if ($family === null) {
            return SlimUtils::renderErrorJSON($response, gettext('Person has no family'), [], 404);
        }

        $familyAdultIds = [];
        foreach ($family->getAdults() as $adult) {
            $familyAdultIds[] = $adult->getId();
        }
        if (!in_array($familyMemberId, $familyAdultIds, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Family member not found'), [], 403);
        }

        $photo = new Photo('Person', $familyMemberId);
        if (!$photo->hasUploadedPhoto()) {
            return SlimUtils::renderErrorJSON($response, gettext('No photo found for this family member'), [], 404);
        }

        return SlimUtils::renderPhoto($response, $photo);
    });

    $group->post('/checkoutAll', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, , $event] = $result;
        $checkedInPeople = $event->getEventAttends();
        
        $checkedOutCount = 0;
        foreach ($checkedInPeople as $attendance) {
            if ($attendance->getCheckoutDate() === null) {
                $event->checkOutPerson($attendance->getPersonId());
                $checkedOutCount++;
            }
        }

        return SlimUtils::renderJSON($response, [
            'success' => true,
            'checkedOut' => $checkedOutCount,
        ]);
    });
});

// Legacy routes - redirect to /device prefix
$app->get('/', function (Request $request, Response $response) {
    $renderer = new PhpRenderer(__DIR__ . '/../templates/kioskDevices/');
    $pageObjects = ['sRootPath' => $_SESSION['sRootPath']];

    return $renderer->render($response, 'sunday-school-class-view.php', $pageObjects);
});
