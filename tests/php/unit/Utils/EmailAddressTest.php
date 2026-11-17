<?php

declare(strict_types=1);

use ChurchCRM\Utils\EmailAddress;
use ChurchCRM\Portal\Auth\MemberSignInRule;
use ChurchCRM\Portal\Auth\SignInReason;
use DateTimeImmutable;
use Tester\Assert;

require dirname(__DIR__, 2) . '/bootstrap.php';

foreach (['jose@x.org', 'JOSE@x.org', 'jose@xn--bcher-kva.org', 'jose@bücher.org', 'one+two@example.technology'] as $email) {
    Assert::true(EmailAddress::isValid($email), $email);
}
foreach (['josé@x.org', 'JOŚE@x.org', 'K@x.org', 'jose', 'jose@', 'jose@bad domain.org', "jose@example.org\r\nBcc: other@example.org"] as $email) {
    Assert::false(EmailAddress::isValid($email), $email);
}
Assert::same('jose@x.org', MemberSignInRule::normalizeEmail(' JOSE@x.org '));
Assert::notSame(MemberSignInRule::normalizeEmail('jose@x.org'), MemberSignInRule::normalizeEmail('josé@x.org'));
Assert::notSame(MemberSignInRule::normalizeEmail('k@x.org'), MemberSignInRule::normalizeEmail('K@x.org'));
$rule = new MemberSignInRule([], [], 0, [], new DateTimeImmutable('2026-11-07'));
Assert::same(SignInReason::NO_MATCH, $rule->findSignInCandidate('K@x.org')->refusal);
Assert::same(SignInReason::NO_MATCH, $rule->findSignInCandidate('josé@x.org')->refusal);
