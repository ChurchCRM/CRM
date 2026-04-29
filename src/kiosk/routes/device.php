<?php

use ChurchCRM\dto\Notification;
use ChurchCRM\dto\Photo;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
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
        // Use configured timezone for "today" calculations
        $today = DateTimeUtils::getToday();
        $currentMonth = (int) $today->format('n');
        $currentDay = (int) $today->format('j');
        $currentYear = (int) $today->format('Y');
        $peopleData = [];

        /**
         * Build the standard person data array entry used by the kiosk.
         *
         * @param \ChurchCRM\model\ChurchCRM\Person $person
         * @param int|null  $statusOverride  Pass an int to override the Propel virtual column (e.g. for guests).
         * @param bool      $isGuest         Whether this person is a walk-in guest.
         */
        $buildPersonEntry = function ($person, ?int $statusOverride = null, bool $isGuest = false) use ($today, $currentMonth, $currentDay, $currentYear): array {
            $photo = new Photo('Person', $person->getId());

            $birthMonth = (int) $person->getBirthMonth();
            $birthDay = (int) $person->getBirthDay();
            $birthYear = $person->getBirthYear();

            $age = null;
            if (!empty($birthYear) && $birthMonth > 0 && $birthDay > 0) {
                $birthDate = DateTimeUtils::createDateTime("$birthYear-$birthMonth-$birthDay");
                $ageInterval = $today->diff($birthDate);
                $age = $ageInterval->y;
            }

            $birthdayThisMonth = ($birthMonth > 0 && $birthMonth === $currentMonth);
            $birthdayUpcoming  = false;
            $birthdayRecent    = false;
            $birthdayToday     = false;
            if ($birthMonth > 0 && $birthDay > 0) {
                $thisBirthday = DateTimeUtils::getToday();
                $thisBirthday->setDate($currentYear, $birthMonth, $birthDay);
                $interval  = $today->diff($thisBirthday);
                $daysDiff  = (int) $interval->format('%r%a');
                if ($daysDiff === 0) {
                    $birthdayToday    = true;
                    $birthdayUpcoming = true;
                } elseif ($daysDiff > 0 && $daysDiff <= 14) {
                    $birthdayUpcoming = true;
                } elseif ($daysDiff < 0 && $daysDiff >= -14) {
                    $birthdayRecent = true;
                }
            }

            $family   = $person->getFamily();
            $familyId = $family !== null ? $family->getId() : null;

            $status = $statusOverride ?? (int) $person->getVirtualColumn('status');

            return [
                'Id'              => $person->getId(),
                'FirstName'       => $person->getFirstName(),
                'LastName'        => $person->getLastName(),
                'Gender'          => $person->getGender(),
                'age'             => $age,
                'birthdayThisMonth' => $birthdayThisMonth,
                'birthdayUpcoming'  => $birthdayUpcoming,
                'birthdayRecent'    => $birthdayRecent,
                'birthdayToday'     => $birthdayToday,
                'birthDay'        => $birthDay > 0 ? $birthDay : null,
                'birthMonth'      => $birthMonth > 0 ? $birthMonth : null,
                'hasPhoto'        => $photo->hasUploadedPhoto(),
                'RoleName'        => $isGuest ? '' : $person->getVirtualColumn('RoleName'),
                'status'          => $status,
                'familyId'        => $familyId,
                'isGuest'         => $isGuest,
            ];
        };

        foreach ($members as $person) {
            $peopleData[] = $buildPersonEntry($person);
        }

        // Append walk-in guests (checked in to this event but not in the group)
        $guests = $assignment->getEventGuests();
        foreach ($guests as $guest) {
            $peopleData[] = $buildPersonEntry($guest, 1, true);
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

        // Verify the person is in the active class roster (group members + event guests)
        // array_column() returns zeros on a Propel ObjectCollection (it reads
        // public props/array keys, not ORM getters), which silently 403s every
        // photo/family request with a confusing "not in roster" error. Iterate
        // and call ->getId() instead. See PR #8706 history; same trap recurred.
        $rosterIds = [];
        foreach ($assignment->getActiveGroupMembers() as $member) {
            $rosterIds[] = (int) $member->getId();
        }
        foreach ($assignment->getEventGuests() as $guest) {
            $rosterIds[] = (int) $guest->getId();
        }
        if (!in_array($personId, $rosterIds, true)) {
            return SlimUtils::renderErrorJSON($response, gettext('Person not in active class roster'), [], 403);
        }

        $photo = new Photo('Person', $personId);

        $response->getBody()->write($photo->getPhotoBytes());

        return $response->withAddedHeader('Content-type', $photo->getPhotoContentType());
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
        // active class roster (group members + event guests) — prevents
        // enumeration outside the assigned group's members.
        // array_column() returns zeros on a Propel ObjectCollection (it reads
        // public props/array keys, not ORM getters), which silently 403s every
        // photo/family request with a confusing "not in roster" error. Iterate
        // and call ->getId() instead. See PR #8706 history; same trap recurred.
        $rosterIds = [];
        foreach ($assignment->getActiveGroupMembers() as $member) {
            $rosterIds[] = (int) $member->getId();
        }
        foreach ($assignment->getEventGuests() as $guest) {
            $rosterIds[] = (int) $guest->getId();
        }
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

    $group->post('/registerGuest', function (Request $request, Response $response) use ($getKioskFromCookie): Response {
        $input = $request->getParsedBody();

        $firstName = InputUtils::sanitizeText($input['FirstName'] ?? '');
        $lastName  = InputUtils::sanitizeText($input['LastName'] ?? '');

        if ($firstName === '' || $lastName === '') {
            return SlimUtils::renderErrorJSON($response, gettext('First name and last name are required'), [], 400);
        }

        $result = requireAcceptedKioskWithEvent($getKioskFromCookie, $response);
        if ($result instanceof Response) {
            return $result;
        }
        [, , $event] = $result;

        // Create a new Person record for the walk-in guest
        $person = new Person();
        $person->setFirstName($firstName);
        $person->setLastName($lastName);
        $person->setDateEntered(DateTimeUtils::getNowDateTime());
        $person->setEnteredBy(0); // 0 = kiosk entry

        // Optional demographic fields
        $birthYear = InputUtils::filterInt($input['BirthYear'] ?? 0);
        if ($birthYear > 0) {
            $person->setBirthYear($birthYear);
        }
        $birthMonth = InputUtils::filterInt($input['BirthMonth'] ?? 0);
        if ($birthMonth > 0 && $birthMonth <= 12) {
            $person->setBirthMonth($birthMonth);
        }
        $birthDay = InputUtils::filterInt($input['BirthDay'] ?? 0);
        if ($birthDay > 0 && $birthDay <= 31) {
            $person->setBirthDay($birthDay);
        }

        // Optional contact fields
        $phone = InputUtils::sanitizeText($input['Phone'] ?? '');
        if ($phone !== '') {
            $person->setCellPhone($phone);
        }
        $email = InputUtils::sanitizeText($input['Email'] ?? '');
        if ($email !== '') {
            $person->setEmail($email);
        }

        $person->save();

        // Immediately check the guest into the current event
        $event->checkInPerson($person->getId());

        LoggerUtils::getAppLogger()->info('registerGuest: Walk-in guest registered and checked in', [
            'personId'  => $person->getId(),
            'name'      => "$firstName $lastName",
            'eventId'   => $event->getId(),
            'eventTitle' => $event->getTitle(),
        ]);

        return SlimUtils::renderJSON($response, [
            'Id'        => $person->getId(),
            'FirstName' => $person->getFirstName(),
            'LastName'  => $person->getLastName(),
            'Gender'    => $person->getGender(),
            'age'       => null,
            'hasPhoto'  => false,
            'isGuest'   => true,
            'familyId'  => null,
            'status'    => 1,
        ]);
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
