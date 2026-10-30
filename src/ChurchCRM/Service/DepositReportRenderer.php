<?php

namespace ChurchCRM\Service;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class DepositReportRenderer
{
    public static function create(): MpdfRenderer
    {
        // Protect these reports and their shared partials until #11014 covers every consumer.
        $twig = new Environment(new FilesystemLoader(__DIR__ . '/../../templates/reports'), [
            'autoescape' => 'html',
        ]);

        return new MpdfRenderer($twig);
    }
}
