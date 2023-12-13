<?php

use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Middleware\MailChimpMiddleware;
use ChurchCRM\Slim\Middleware\Request\FamilyAPIMiddleware;
use ChurchCRM\Slim\Middleware\Request\PersonAPIMiddleware;
use ChurchCRM\Slim\Request\SlimUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpNotFoundException;
use Slim\Routing\RouteCollectorProxy;

$app->group('/mailchimp/', function (RouteCollectorProxy $group) {
    $group->get('list/{id}', 'getMailchimpList');
    $group->get('list/{id}/missing', 'getMailchimpEmailNotInCRM');
    $group->get('list/{id}/not-subscribed', 'getMailChimpMissingSubscribed');
    $group->get('person/{personId}', 'getPersonStatus')->add(new PersonAPIMiddleware());
    $group->get('family/{familyId}', 'getFamilyStatus')->add(new FamilyAPIMiddleware());
})->add(MailChimpMiddleware::class);

function getMailchimpList(Request $request, Response $response, array $args): Response
{
    $listId = $args['id'];

    $mailchimpService = $request->getAttribute('mailchimpService');
    $list = $mailchimpService->getList($listId);

    $response->getBody()->write(json_encode(['list' => $list]));

    return $response->withHeader('Content-Type', 'application/json');
}

function getMailchimpEmailNotInCRM(Request $request, Response $response, array $args): Response
{
    $listId = $args['id'];

    $mailchimpService = $request->getAttribute('mailchimpService');
    $list = $mailchimpService->getList($listId);
    if (!$list) {
        throw new HttpNotFoundException($request, gettext('List not found'));
    }

    $mailchimpListMembers = $list['members'];

    foreach (getPeopleWithEmails() as $person) {
        $inList = checkEmailInList($person->getEmail(), $mailchimpListMembers);
        if ($inList > 0) {
            array_splice($mailchimpListMembers, $inList, 1);
        } else {
            $inList = $inList = checkEmailInList($person->getWorkEmail(), $mailchimpListMembers);
            if ($inList > 0) {
                array_splice($mailchimpListMembers, $inList, 1);
            }
        }
    }
    LoggerUtils::getAppLogger()->debug('MailChimp list ' . $listId . ' now has ' . count($mailchimpListMembers) . ' members');

    return SlimUtils::renderJSON($response, ['id' => $list['id'], 'name' => $list['name'], 'members' => $mailchimpListMembers]);
}

function getMailChimpMissingSubscribed(Request $request, Response $response, array $args): Response
{
    $listId = $args['id'];

    $mailchimpService = $request->getAttribute('mailchimpService');
    $list = $mailchimpService->getList($listId);
    if (!$list) {
        throw new HttpNotFoundException($request, gettext('List not inList'));
    }

    $mailchimpListMembers = $list['members'];
    $personsNotInMailchimp = [];
    foreach (getPeopleWithEmails() as $person) {
        if (!empty($person->getEmail()) || !empty($person->getWorkEmail())) {
            $inList = false;
            if (!empty($person->getEmail()) && checkEmailInList($person->getEmail(), $mailchimpListMembers)) {
                $inList = true;
            }

            if (!$inList && !empty($person->getWorkEmail())) {
                $inList = checkEmailInList($person->getWorkEmail(), $mailchimpListMembers);
            }

            if (!$inList) {
                $emails = [];
                if (!empty($person->getEmail())) {
                    array_push($emails, $person->getEmail());
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
    }
    LoggerUtils::getAppLogger()->debug('MailChimp list ' . $listId . ' now has ' . count($mailchimpListMembers) . ' members');

    return SlimUtils::renderJSON($response, ['id' => $list['id'], 'name' => $list['name'], 'members' => $personsNotInMailchimp]);
}

function getFamilyStatus(Request $request, Response $response, array $args): Response
{
    $family = $request->getAttribute('family');
    $mailchimpService = $request->getAttribute('mailchimpService');
    $emailToLists = [];
    if (!empty($family->getEmail())) {
        $emailToLists[] = [
            'email' => $family->getEmail(),
            'emailMD5' => md5($family->getEmail()),
            'list' => $mailchimpService->isEmailInMailChimp($family->getEmail())
        ];
    }
    $response->getBody()->write(json_encode($emailToLists));

    return $response->withHeader('Content-Type', 'application/json');
}

function getPersonStatus(Request $request, Response $response, array $args): Response
{
    $person = $request->getAttribute('person');
    $mailchimpService = $request->getAttribute('mailchimpService');
    $emailToLists = [];
    if (!empty($person->getEmail())) {
        $emailToLists[] = [
            'email' => $person->getEmail(),
            'emailMD5' => md5($person->getEmail()),
            'list' => $mailchimpService->isEmailInMailChimp($person->getEmail())
        ];
    }
    if (!empty($person->getWorkEmail())) {
        $emailToLists[] = [
            'email' => $person->getWorkEmail(),
            'emailMD5' => md5($person->getWorkEmail()),
            'list' => $mailchimpService->isEmailInMailChimp($person->getWorkEmail())
        ];
    }
    $response->getBody()->write(json_encode($emailToLists));

    return $response->withHeader('Content-Type', 'application/json');
}

function getPeopleWithEmails()
{
    $list = PersonQuery::create()
        ->filterByEmail(null, Criteria::NOT_EQUAL)
        ->_or()
        ->filterByWorkEmail(null, Criteria::NOT_EQUAL)
        ->orderById()
        ->find();

    return $list;
}

function checkEmailInList($email, $memberList)
{
    $email = trim(strtolower($email));
    $key = array_search($email, array_column($memberList, 'email'));
    if ($key > 0) {
        return true;
    }

    return false;
}
