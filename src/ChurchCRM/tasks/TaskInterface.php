<?php

namespace ChurchCRM\Tasks;

interface TaskInterface
{
    public function isActive(): bool;

    public function isAdmin(): bool;

    public function getLink(): string;

    public function getTitle(): string;

    public function getDesc(): string;
}
