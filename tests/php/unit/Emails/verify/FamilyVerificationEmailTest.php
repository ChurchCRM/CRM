<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Emails\verify\FamilyVerificationEmail;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';

SystemURLs::init('', ['https://crm.example.org'], APP_ROOT);
SystemConfig::init();

// #11145 — the family verify email links to the member portal, not to a
// /external/verify/{token} page and not to a PDF snapshot.
$email = new FamilyVerificationEmail(['ann@example.org'], 'Campbell');
$tokens = $email->getTokens();

Assert::same(
    'https://crm.example.org/portal/family',
    $tokens['fullURL'],
    'the call-to-action links to the portal My Family page'
);
Assert::false(
    str_contains($tokens['fullURL'], '/external/verify/'),
    'no bearer-token URL is left in the link'
);
Assert::same(
    'Please verify your family\'s information',
    $tokens['preheader'],
    'the preheader is unchanged'
);

// The ** Updated ** variant keeps the same portal link.
$updated = (new FamilyVerificationEmail(['ann@example.org'], 'Campbell', true))->getTokens();
Assert::same('https://crm.example.org/portal/family', $updated['fullURL'], 'the updated variant links to the portal too');
