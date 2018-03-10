<?php

namespace ChurchCRM\dto\Notification;


class DevNotification
{
    private $targetVersion;
    private $adminOnly;
    private $homePageOnly;
    private $title;
    private $message;
    private $style;

    /**
     * DevNotification constructor.
     * @param $targetVersion
     * @param $adminOnly
     * @param $homePageOnly
     * @param $title
     * @param $message
     * @param $style
     */
    public function __construct($targetVersion, $adminOnly, $homePageOnly, $title, $message, $style)
    {
        $this->targetVersion = $targetVersion;
        $this->adminOnly = $adminOnly;
        $this->homePageOnly = $homePageOnly;
        $this->title = $title;
        $this->message = $message;
        $this->style = $style;
    }

    /**
     * @return mixed
     */
    public function getTargetVersion()
    {
        return $this->targetVersion;
    }

    /**
     * @return mixed
     */
    public function getAdminOnly()
    {
        return $this->adminOnly;
    }

    /**
     * @return mixed
     */
    public function getHomePageOnly()
    {
        return $this->homePageOnly;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return mixed
     */
    public function getStyle()
    {
        return $this->style;
    }

}
