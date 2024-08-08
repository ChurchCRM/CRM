<?php

namespace ChurchCRM\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class GettextExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('gettext', 'gettext'),
        ];
    }
}
