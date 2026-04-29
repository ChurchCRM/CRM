<?php

namespace ChurchCRM\Plugin;

/**
 * Thrown by {@see PluginInstaller} when an install would overwrite an
 * existing on-disk plugin directory.
 *
 * Lets the API layer map the failure to HTTP 409 Conflict via an
 * `instanceof` check instead of substring-matching the exception
 * message, which is brittle to wording changes.
 */
class PluginAlreadyInstalledException extends \RuntimeException
{
}
