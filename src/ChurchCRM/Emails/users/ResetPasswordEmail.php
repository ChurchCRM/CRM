<?php

namespace ChurchCRM\Emails;


class ResetPasswordEmail extends BaseUserEmail
{

    protected $password;

    public function __construct($user, $password) {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("You can reset your ChurchCRM password by clicking this link:"));
        array_push($msg, "<a href='" . $this->getLink() . "'>" . gettext("Change Password"). "</a>");
        array_push($msg, gettext('Username') . ": " . $this->user->getUserName());
        array_push($msg, gettext('New Password') . ": " . $this->password);
        return implode("<p/>", $msg);
    }
}
