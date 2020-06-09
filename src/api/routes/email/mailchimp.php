<?php

use ChurchCRM\PersonQuery;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Slim\Middleware\MailChimpMiddleware;
use Propel\Runtime\ActiveQuery\Criteria;
use Slim\Http\Request;
use Slim\Http\Response;

$app->group('/mailchimp/', function () {
    $this->get('{id}/missing', 'getMailchimpEmailNotInCRM');
    $this->get('{id}/not-subscribed', 'getMailChimpMissingSubscribed');

})->add(new MailChimpMiddleware());

function getMailchimpEmailNotInCRM(Request $request, Response $response, array $args) {

    $listId = $args['id'];

    $mailchimpService = $request->getAttribute("mailchimpService");
    $mailchimpListMembers = $mailchimpService->getListMembers($listId);

    $People = PersonQuery::create()
        ->filterByEmail(null, Criteria::NOT_EQUAL)
        ->_or()
        ->filterByWorkEmail(null, Criteria::NOT_EQUAL)
        ->find();


    foreach($People as $Person)
    {
        $key = array_search(strtolower($Person->getEmail()), $mailchimpListMembers);
        if ($key > 0) {
            LoggerUtils::getAppLogger()->debug("found " . $Person->getEmail());
            array_splice($mailchimpListMembers, $key, 1);
        }
        $key = array_search($Person->getWorkEmail(), $mailchimpListMembers);
        if ($key > 0) {
            array_splice($mailchimpListMembers, $key, 1);
        }
    }
    LoggerUtils::getAppLogger()->debug("MailChimp list ". $listId . " now has ". count($mailchimpListMembers) . " members");

    return $response->withJson($mailchimpListMembers);
}

function getMailChimpMissingSubscribed(Request $request, Response $response, array $args) {
    $listId = $args['id'];

    $mailchimpService = $request->getAttribute("mailchimpService");
    $mailchimpListMembers = $mailchimpService->getListMembers($listId);

    $People = PersonQuery::create()
        ->filterByEmail(null, Criteria::NOT_EQUAL)
        ->_or()
        ->filterByWorkEmail(null, Criteria::NOT_EQUAL)
        ->find();

    $personsNotInMailchimp = [];
    foreach($People as $Person)
    {
        $found = false;
        $key = array_search(strtolower($Person->getEmail()), $mailchimpListMembers);
        if ($key > 0 ) {
            $found= true;
        }
        $key = array_search($Person->getWorkEmail(), $mailchimpListMembers);
        if ($key > 0) {
            $found= true;
        }

        if (!$found) {
            $emails = [];
            if (!empty($Person->getEmail())) {
                array_push($emails, $Person->getEmail());
            }
            if (!empty($Person->getWorkEmail())) {
                array_push($emails, $Person->getWorkEmail());
            }
            array_push($personsNotInMailchimp, ["id" => $Person->getId(),
                "name" => $Person->getFullName(),
                "emails" => $emails
            ]);
        }
    }
    LoggerUtils::getAppLogger()->debug("MailChimp list ". $listId . " now has ". count($mailchimpListMembers) . " members");

    return $response->withJson($personsNotInMailchimp);
}
