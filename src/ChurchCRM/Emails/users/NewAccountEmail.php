<?php

namespace ChurchCRM\Emails;


class NewAccountEmail extends BaseUserEmail
{

    protected $password;

    public function __construct($user, $password) {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
        return gettext("Your CRM Account");
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("A ChurchCRM account was created for you:"));
        array_push($msg, "<a href='" . $this->getLink() . "'>" . gettext("Login"). "</a>");
        array_push($msg, gettext('Username') . ": " . $this->user->getUserName());
        array_push($msg, gettext('New Password') . ": " . $this->password);
        return implode("<p/>", $msg);
    }
}
