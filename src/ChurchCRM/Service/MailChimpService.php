<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use \DrewM\MailChimp\MailChimp;
use ChurchCRM\Utils\LoggerUtils;
use ChurchCRM\Utils\ExecutionTime;

class ListEmailFilter {
  private $email;
  
  function __construct($emailAddress)
  {
    $this->email = $emailAddress;
  }
  public function isEmailInList($list) {
    foreach ($list['members'] as $listMember) {
      if (strcmp(strtolower($listMember['email_address']), strtolower($this->email)) == 0) {
        return true;
      }
    }
    return false;
  }       
}

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
        LoggerUtils::getAppLogger()->debug("Updating MailChimp List Cache");
        $time = new ExecutionTime;
        $lists = $this->myMailchimp->get("lists")['lists'];
        LoggerUtils::getAppLogger()->debug("MailChimp list enumeration took: ". $time->getMiliseconds(). " ms.  Found ".count($lists)." lists");
        foreach($lists as &$list) {
          $listmembers = $this->myMailchimp->get('lists/'.$list['id'].'/members',['count' => 100000]);
          $list['members'] = $listmembers['members'];
        }
        LoggerUtils::getAppLogger()->debug("MailChimp list and membership update took: ". $time->getMiliseconds(). " ms");
        $_SESSION['MailChimpLists'] = $lists;
      }
      else{
        LoggerUtils::getAppLogger()->debug("Using cached MailChimp List");
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
            $lists = array_filter($lists, array(new ListEmailFilter($email),'isEmailInList'));
            $listNames = array_map(function ($list) { return $list['name']; }, $lists);
            $listMemberships = implode(', ', $listNames);
            LoggerUtils::getAppLogger()->debug($email. "is a member of ".$listMemberships);

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
