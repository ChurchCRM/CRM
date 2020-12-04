<?php

use ChurchCRM\PersonQuery;
use ChurchCRM\Slim\Middleware\MailChimpMiddleware;
use ChurchCRM\Slim\Middleware\Request\FamilyAPIMiddleware;
use ChurchCRM\Slim\Middleware\Request\PersonAPIMiddleware;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/mailchimp/', function () {
    $this->get('list/{id}', 'getMailchimpList');
    $this->get('list/{id}/missing', 'getMailchimpEmailNotInCRM');
    $this->get('list/{id}/not-subscribed', 'getMailChimpMissingSubscribed');
    $this->get('person/{personId}', 'getPersonStatus')->add(new PersonAPIMiddleware());
    $this->get('family/{familyId}', 'getFamilyStatus')->add(new FamilyAPIMiddleware());
})->add(new MailChimpMiddleware());


function getMailchimpList(Request $request, Response $response, array $args)
{

    $listId = $args['id'];

    $mailchimpService = $request->getAttribute("mailchimpService");
    $list = $mailchimpService->getList($listId);

    return $response->withJson(["list" => $list]);
}

function getMailchimpEmailNotInCRM(Request $request, Response $response, array $args)
{

    $listId = $args['id'];

    $mailchimpService = $request->getAttribute("mailchimpService");
    $list = $mailchimpService->getList($listId);
    if ($list) {
        $mailchimpListMembers = $list["members"];

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
        LoggerUtils::getAppLogger()->debug("MailChimp list " . $listId . " now has " . count($mailchimpListMembers) . " members");

        return $response->withJson(["id" => $list["id"], "name" => $list["name"], "members" => $mailchimpListMembers]);
    } else {
        return $response->withStatus(404, gettext("List not found"));
    }
}

function getMailChimpMissingSubscribed(Request $request, Response $response, array $args)
{
    $listId = $args['id'];

    $mailchimpService = $request->getAttribute("mailchimpService");
    $list = $mailchimpService->getList($listId);
    if ($list) {
        $mailchimpListMembers = $list["members"];
        $personsNotInMailchimp = [];
        foreach (getPeopleWithEmails() as $person) {
            if (!empty($person->getEmail()) || !empty($person->getWorkEmail())) {
                $inList = false;
                if (!empty($person->getEmail()) && checkEmailInList($person->getEmail(), $mailchimpListMembers)) {
                    $inList = true;
                }

                if (!found && !empty($person->getWorkEmail())) {
                    $inList = checkEmailInList($person->getWorkEmail(), $mailchimpListMembers);
                }

                if (!$inList) {
                    $emails = [];
                    if (!empty($person->getEmail())) {
                        array_push($emails, $person->getEmail());
                    }
                    if (!empty($person->getWorkEmail())) {
                        array_push($emails, $person->getWorkEmail());
                    }
                    array_push($personsNotInMailchimp, ["id" => $person->getId(),
                        "name" => $person->getFullName(),
                        "emails" => $emails
                    ]);
                }
            }
        }
        LoggerUtils::getAppLogger()->debug("MailChimp list " . $listId . " now has " . count($mailchimpListMembers) . " members");

        return $response->withJson(["id" => $list["id"], "name" => $list["name"] ,"members" =>$personsNotInMailchimp]);
    } else {
        return $response->withStatus(404, gettext("List not inList"));
    }
}

function getFamilyStatus(Request $request, Response $response, array $args)
{
    $family = $request->getAttribute("family");
    $mailchimpService = $request->getAttribute("mailchimpService");
    $emailToLists = [];
    if (!empty($family->getEmail())) {
        array_push($emailToLists, ["email" => $family->getEmail(), "emailMD5" => md5($family->getEmail()),
            "list" => $mailchimpService->isEmailInMailChimp($family->getEmail())]);
    }
    return $response->withJson($emailToLists);
}

function getPersonStatus(Request $request, Response $response, array $args)
{
    $person = $request->getAttribute("person");
    $mailchimpService = $request->getAttribute("mailchimpService");
    $emailToLists = [];
    if (!empty($person->getEmail())) {
        array_push($emailToLists, ["email" => $person->getEmail(), "emailMD5" => md5($person->getEmail()),
            "list" => $mailchimpService->isEmailInMailChimp($person->getEmail())]);
    }
    if (!empty($person->getWorkEmail())) {
        array_push($emailToLists, ["email" => $person->getWorkEmail(), "emailMD5" => md5($person->getWorkEmail()),
            "list" => $mailchimpService->isEmailInMailChimp($person->getWorkEmail())]);
    }
    return $response->withJson($emailToLists);
}

function getPeopleWithEmails() {
    $list = PersonQuery::create()
        ->filterByEmail(null, Criteria::NOT_EQUAL)
        ->_or()
        ->filterByWorkEmail(null, Criteria::NOT_EQUAL)
        ->orderById()
        ->find();

    return $list;
}

function checkEmailInList($email, $memberList) {
    $email = trim(strtolower($email));
    $key = array_search($email, array_column($memberList, "email"));
    if ($key > 0) {
        return true;
    }
    return false;
}
