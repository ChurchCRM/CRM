<?php

namespace ChurchCRM\Emails;


class AccountDeletedEmail extends BaseUserEmail
{

    protected $password;

    public function __construct($user, $password) {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
        return gettext("Your CRM Account was Deleted");
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("Your CRM Account was Deleted, if you think this is an error, please contact your admin"));
        return implode("<p/>", $msg);
    }
}
