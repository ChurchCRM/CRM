<?php

namespace ChurchCRM\Tasks;

interface iTask
{
  public function isActive(): bool;
  public function isAdmin(): bool;
  public function getLink(): string;
  public function getTitle(): string;
  public function getDesc(): string;
}
