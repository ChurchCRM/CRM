<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use \DrewM\MailChimp\MailChimp;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\ExecutionTime;
use PHPMailer\PHPMailer\Exception;

class MailChimpService
{
    private $isActive = false;
    private $myMailchimp;

    public function __construct()
    {
        if (!empty(SystemConfig::getValue('sMailChimpApiKey'))) {
            $this->isActive = true;
            $this->myMailchimp = new MailChimp(SystemConfig::getValue('sMailChimpApiKey'));
        }
    }

    public function isActive()
    {
        return $this->isActive;
    }

    private function getListsFromCache()
    {
        if (!isset($_SESSION['MailChimpLists'])) {
            LoggerUtils::getAppLogger()->debug("Updating MailChimp List Cache");
            $time = new ExecutionTime;
            $lists = $this->myMailchimp->get("lists")['lists'];
            LoggerUtils::getAppLogger()->debug("MailChimp list enumeration took: " . $time->getMiliseconds() . " ms.  Found " . count($lists) . " lists");
            foreach ($lists as &$list) {
                $listmembers = $this->myMailchimp->get('lists/' . $list['id'] . '/members',
                    [
                        'count' => $list['stats']["member_count"],
                        "fields" => "members.email_address",
                        "status" => "subscribed"
                    ]);
                $list['members'] = $listmembers['members'];
                LoggerUtils::getAppLogger()->debug("MailChimp list ". $list['id'] . " membership ". count($list['members']));

            }
            LoggerUtils::getAppLogger()->debug("MailChimp list and membership update took: " . $time->getMiliseconds() . " ms");
            $_SESSION['MailChimpLists'] = $lists;
        } else {
            LoggerUtils::getAppLogger()->debug("Using cached MailChimp List");
        }
        return $_SESSION['MailChimpLists'];
    }

    public function isEmailInMailChimp($email)
    {
        if (empty($email)) {
            return new Exception(gettext('No email passed in'));
        }

        if (!$this->isActive) {
            return new Exception(gettext('Mailchimp is not active'));
        }

        $lists = $this->getListsFromCache();
        $listsStatus = [];
        foreach ($lists as $list) {
            $data = $this->myMailchimp->get("lists/" . $list["id"] . "/members/" . md5($email));
            LoggerUtils::getAppLogger()->debug($email . " is " . $data["status"] . " to " . $list["name"]);
            array_push($listsStatus, ["name" => $list["name"], "status" => $data["status"], "stats" => $data["stats"]]);
        }
        return $listsStatus;
    }

    public function getLists()
    {
        if (!$this->isActive) {
            return new Exception(gettext('Mailchimp is not active'));
        }
        return $this->getListsFromCache();
    }
}
