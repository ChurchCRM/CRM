<?php

namespace ChurchCRM\dto\Notification;
use JsonSerializable;

class UiNotification implements JsonSerializable
{
    private $title;
    private $message;
    private $url;
    private $type;
    private $icon;
    private $delay;
    private $placement;
    private $align;

    /**
     * UiNotification constructor.
     * @param $title
     * @param $message
     * @param $url
     * @param $type
     * @param $icon
     * @param $delay
     * @param $placement
     * @param $align
     */
    public function __construct($title, $icon, $url ="", $message="", $type="info",  $delay=4000, $placement ="top", $align = "right")
    {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
        $this->type = $type;
        $this->icon = $icon;
        $this->delay = $delay;
        $this->placement = $placement;
        $this->align = $align;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @return int
     */
    public function getDelay()
    {
        return $this->delay;
    }

    /**
     * @return string
     */
    public function getPlacement()
    {
        return $this->placement;
    }

    /**
     * @return string
     */
    public function getAlign()
    {
        return $this->align;
    }


    public function jsonSerialize()
    {
        return get_object_vars($this);
    }

}
