<?php

namespace ChurchCRM\dto\Notification;

use JsonSerializable;

class UiNotification implements JsonSerializable
{
    private string $id;
    private string $dismissSettingKey;
    private string $title;
    private string $message;
    private string $url;
    private string $type;
    private string $icon;

    public function __construct(
        string $id,
        string $dismissSettingKey,
        string $title,
        string $message = '',
        string $url = '',
        string $type = 'info',
        string $icon = 'info-circle'
    ) {
        $this->id = $id;
        $this->dismissSettingKey = $dismissSettingKey;
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
        $this->type = $type;
        $this->icon = $icon;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDismissSettingKey(): string
    {
        return $this->dismissSettingKey;
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

    public function jsonSerialize(): array
    {
        return [
            'id'                => $this->id,
            'dismissSettingKey' => $this->dismissSettingKey,
            'title'             => $this->title,
            'message'           => $this->message,
            'url'               => $this->url,
            'type'              => $this->type,
            'icon'              => $this->icon,
        ];
    }
}
