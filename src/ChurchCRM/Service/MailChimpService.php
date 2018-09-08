<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use \DrewM\MailChimp\MailChimp;
use ChurchCRM\Utils\LoggerUtils;

class MailChimpService
{
    private $isActive = false;
    private $myMailchimp;
    private $lists;

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
    private function getListsFromCache(){
      if (!isset($_SESSION['MailChimpLists'])){
        LoggerUtils::getAppLogger()->info("Updating MailChimp List Cache");
        $lists = $this->myMailchimp->get("lists")['lists'];
        foreach($lists as &$list) {
          $listmembers = $this->myMailchimp->get('lists/'.$list['id'].'/members',['count' => 100000]);
          $list['members'] = $listmembers['members'];
        }
        $_SESSION['MailChimpLists'] = $lists;
      }
      else{
        LoggerUtils::getAppLogger()->info("Using cached MailChimp List");
      }
      return $_SESSION['MailChimpLists'];
    }

    public function isEmailInMailChimp($email)
    {
        if (!$this->isActive) {
            return 'Mailchimp is not active';
        }
        
        if ($email == '') {
            return 'No email';
        }
        
        try {
            $lists = $this->getListsFromCache();
            $listNames = [];
            foreach($lists as $list) {
              foreach ($list['members'] as $listMember) {
                if (strcmp(strtolower($listMember['email_address']), strtolower($email)) == 0) {
                  LoggerUtils::getAppLogger()->info("Found $email in ".$list['name']);
                  array_push($listNames, $list['name']);
                }
              }
            }

            $listMemberships = implode(',', $listNames);
            LoggerUtils::getAppLogger()->info($email. "is a member of ".$listMemberships);

            return $listMemberships;
        } catch (\Mailchimp_Invalid_ApiKey $e) {
            return 'Invalid ApiKey';
        } catch (\Mailchimp_List_NotSubscribed $e) {
            return '';
        } catch (\Mailchimp_Email_NotExists $e) {
            return '';
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function getLists()
    {
        if (!$this->isActive) {
          return 'Mailchimp is not active';
        }
        try {
            $result = $this->getListsFromCache();
            
            return $result;
        } catch (\Mailchimp_Invalid_ApiKey $e) {
            return 'Invalid ApiKey';
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
