<?php

namespace ChurchCRM\dto\Notification;

use JsonSerializable;

class UiNotification implements JsonSerializable
{
    private string $title;
    private string $message;
    private string $url;
    private string $type;
    private string $icon;
    private int $delay;
    private string $placement;
    private string $align;

    public function __construct(
        string $title,
        string $icon,
        string $url = '',
        string $message = '',
        string $type = 'info',
        int $delay = 4000,
        string $placement = 'top',
        string $align = 'right'
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
        $this->type = $type;
        $this->icon = $icon;
        $this->delay = $delay;
        $this->placement = $placement;
        $this->align = $align;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getDelay(): int
    {
        return $this->delay;
    }

    public function getPlacement(): string
    {
        return $this->placement;
    }

    public function getAlign(): string
    {
        return $this->align;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}
