<?php

// English remains available when gettext itself is missing.
$hasGettext = function_exists('gettext');
$escape = static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $escape(($hasGettext ? gettext('ChurchCRM System Check') : 'ChurchCRM System Check')) ?></title>
    <link rel="stylesheet" href="<?= $escape($requirementsRootPath) ?>/skin/v2/setup.min.css">
</head>
<body>
<main id="main-content" class="container py-4">
    <div class="card">
        <div class="card-body">
            <h1 class="card-title"><?= $escape(($hasGettext ? gettext('ChurchCRM System Check') : 'ChurchCRM System Check')) ?></h1>
            <p role="alert" class="text-danger"><?= $escape(($hasGettext ? gettext('Some PHP requirements are missing. ChurchCRM needs these before it can continue.') : 'Some PHP requirements are missing. ChurchCRM needs these before it can continue.')) ?></p>
            <p><?= $escape(($hasGettext ? gettext('Enable the missing PHP extensions in your web server configuration, restart the web server, then reload this page. If you use a hosting provider, ask them to enable the listed extensions.') : 'Enable the missing PHP extensions in your web server configuration, restart the web server, then reload this page. If you use a hosting provider, ask them to enable the listed extensions.')) ?></p>
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead><tr><th scope="col"><?= $escape(($hasGettext ? gettext('Requirement') : 'Requirement')) ?></th><th scope="col"><?= $escape(($hasGettext ? gettext('Status') : 'Status')) ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($phpPrerequisites as $check): ?>
                        <?php $satisfied = $check->isPrerequisiteMet(); ?>
                        <tr>
                            <th scope="row" class="text-wrap"><?= $escape($check->getName()) ?></th>
                            <td class="<?= $satisfied ? 'text-success' : 'text-danger' ?>"><?= $escape($satisfied ? ($hasGettext ? gettext('Passed') : 'Passed') : ($hasGettext ? gettext('Failed') : 'Failed')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>
</body>
</html>
