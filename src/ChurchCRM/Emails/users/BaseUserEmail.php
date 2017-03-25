<?php
namespace ChurchCRM\Emails;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\User;


abstract class BaseUserEmail extends BaseEmail
{
    protected $user;

    /**
     * BaseUserEmail constructor.
     * @param $user User
     */
    public function __construct($user)
    {
        parent::__construct([$user->getEmail()]);
        $this->user = $user;
        $this->mail->Subject = SystemConfig::getValue("sChurchName") . ": " . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    protected abstract function getSubSubject();

    protected function buildMessage(){
        $msg = array();
        array_push($msg, $this->buildMessageHeader());
        array_push($msg, $this->buildMessageBody());
        array_push($msg, $this->buildMessageFooter());
        return implode("<p/>", $msg);
    }

    protected function buildMessageHeader()
    {
        return SystemConfig::getValue('sDear') ." " . $this->user->getFullName();
    }

    protected abstract function buildMessageBody();

    protected function buildMessageFooter()
    {
        return SystemConfig::getValue('sConfirmSincerely') . ",<br/>" . SystemConfig::getValue("sConfirmSigner");
    }

    protected function getLink()
    {
        return SystemURLs::getURL() . "Login.php?username=" . $this->user->getUserName();
    }
}
