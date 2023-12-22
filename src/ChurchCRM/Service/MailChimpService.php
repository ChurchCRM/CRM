<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\ExecutionTime;
use ChurchCRM\Utils\LoggerUtils;
use DrewM\MailChimp\MailChimp;
use PHPMailer\PHPMailer\Exception;

class MailChimpService
{
    private bool $hasKey = false;
    private bool $isActive = false;
    private ?\DrewM\MailChimp\MailChimp $myMailchimp = null;

    public function __construct()
    {
        if (!empty(SystemConfig::getValue('sMailChimpApiKey'))) {
            $this->hasKey = true;
            $this->myMailchimp = new MailChimp(SystemConfig::getValue('sMailChimpApiKey'));
        }
    }

    public function isActive(): bool
    {
        if ($this->isActive) {
            return true;
        }
        if ($this->hasKey) {
            $rootAPI = $this->myMailchimp->get('');
            if ($rootAPI['total_subscribers'] > 0) {
                $this->isActive = true;

                return true;
            }
        }

        return false;
    }

    private function getListsFromCache()
    {
        if (!isset($_SESSION['MailChimpLists'])) {
            LoggerUtils::getAppLogger()->debug('Updating MailChimp List Cache');
            $time = new ExecutionTime();
            $lists = $this->myMailchimp->get('lists')['lists'];
            LoggerUtils::getAppLogger()->debug('MailChimp list enumeration took: ' . $time->getMilliseconds() . ' ms.  Found ' . count($lists) . ' lists');
            foreach ($lists as &$list) {
                $list['members'] = [];
                $listmembers = $this->myMailchimp->get(
                    'lists/' . $list['id'] . '/members',
                    [
                        'count'  => $list['stats']['member_count'],
                        'fields' => 'members.id,members.email_address,members.status,members.merge_fields',
                        'status' => 'subscribed',
                    ]
                );
                foreach ($listmembers['members'] as $member) {
                    $list['members'][] = [
                        'email' => strtolower($member['email_address']),
                        'first' => $member['merge_fields']['FNAME'],
                        'last' => $member['merge_fields']['LNAME'],
                        'status' => $member['status'],
                    ];
                }
                LoggerUtils::getAppLogger()->debug('MailChimp list ' . $list['id'] . ' membership ' . count($list['members']));
            }
            LoggerUtils::getAppLogger()->debug('MailChimp list and membership update took: ' . $time->getMilliseconds() . ' ms');
            $_SESSION['MailChimpLists'] = $lists;
        } else {
            LoggerUtils::getAppLogger()->debug('Using cached MailChimp List');
        }

        return $_SESSION['MailChimpLists'];
    }

    public function isEmailInMailChimp(?string $email)
    {
        if (empty($email)) {
            return new Exception(gettext('No email passed in'));
        }

        if (!$this->isActive()) {
            return new Exception(gettext('Mailchimp is not active'));
        }

        $lists = $this->getListsFromCache();
        $listsStatus = [];
        foreach ($lists as $list) {
            $data = $this->myMailchimp->get('lists/' . $list['id'] . '/members/' . md5($email));
            LoggerUtils::getAppLogger()->debug($email . ' is ' . $data['status'] . ' to ' . $list['name']);
            $listsStatus[] = ['name' => $list['name'], 'status' => $data['status'], 'stats' => $data['stats']];
        }

        return $listsStatus;
    }

    public function getLists()
    {
        if (!$this->isActive()) {
            return new Exception(gettext('Mailchimp is not active'));
        }

        return $this->getListsFromCache();
    }

    public function getList($listId)
    {
        $mailchimpLists = $this->getLists();
        foreach ($mailchimpLists as $mailchimpList) {
            if ($listId == $mailchimpList['id']) {
                return $mailchimpList;
            }
        }
    }
}
