<?php
/**
 * Created by PhpStorm.
 * User: georg
 * Date: 3/14/2017
 * Time: 11:13 PM
 */

namespace ChurchCRM\Emails;


class NewPasswordEmail extends BaseUserEmail
{

    protected $password;

    public function __construct($user, $password) {
        $this->password = $password;
        parent::__construct($user);
    }

    protected function getSubSubject()
    {
        return gettext("Your CRM Password");
    }

    protected function buildMessageBody()
    {
        $msg = array();
        array_push($msg, gettext("We have received your request to reset your password. Here are your password reset instructions."));
        array_push($msg, gettext('Username') . ": " . $this->user->getUserName());
        array_push($msg, gettext('New Password') . ": " . $this->password);
        array_push($msg, "<a href='" . $this->getLink() . "'>" . gettext("Follow this link to reset your password."). "</a>");
        return implode("<p/>", $msg);
    }
}
