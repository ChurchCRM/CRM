<?php

namespace ChurchCRM\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Makes the application's translation functions available to Twig templates —
 * the email templates and, since the Member Portal, the portal's theme
 * templates. `ngettext` is what lets a template pluralise without building the
 * sentence in PHP first.
 */
class GettextExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('gettext', 'gettext'),
            new TwigFunction('ngettext', 'ngettext'),
        ];
    }
}
