<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\ComposerEmail;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Resolves composer recipients from person / family ids and sends one
 * ComposerEmail per recipient.
 *
 * The API never accepts raw addresses: the server owns the recipient list, so
 * it can apply the do-not-email property, skip the deceased and the
 * address-less, deduplicate, and report every skipped recipient by name.
 *
 * Recipient shape (also the shape of the `recipients` arrays returned by the
 * /cart/emails, /people/emails and /groups/{id}/emails list endpoints):
 *   ['personId' => ?int, 'familyId' => ?int, 'name' => string, 'email' => string]
 */
class EmailComposerService
{
    /** Hard cap per request; larger lists belong to a mailing-list tool. */
    public const MAX_RECIPIENTS = 500;

    public const SKIP_NOT_FOUND = 'not-found';
    public const SKIP_NO_EMAIL = 'no-email';
    public const SKIP_DO_NOT_EMAIL = 'do-not-email';
    public const SKIP_DECEASED = 'deceased';
    public const SKIP_INACTIVE = 'inactive';
    public const SKIP_DUPLICATE = 'duplicate-address';

    /**
     * @param int[] $personIds
     * @param int[] $familyIds
     *
     * @return array{recipients: list<array{personId: ?int, familyId: ?int, name: string, email: string}>, skipped: list<array{personId: ?int, familyId: ?int, name: string, reason: string}>}
     */
    public function resolveRecipients(array $personIds, array $familyIds): array
    {
        $personIds = self::normalizeIds($personIds);
        $familyIds = self::normalizeIds($familyIds);

        $recipients = [];
        $skipped = [];
        $seenAddresses = [];

        if ($personIds !== []) {
            $persons = [];
            foreach (PersonQuery::create()->filterById($personIds)->find() as $person) {
                $persons[(int) $person->getId()] = $person;
            }
            $doNotEmailSet = (new PersonService())->buildDoNotEmailSet($personIds);

            foreach ($personIds as $personId) {
                $person = $persons[$personId] ?? null;
                if ($person === null) {
                    $skipped[] = self::skip($personId, null, '', self::SKIP_NOT_FOUND);
                    continue;
                }
                $name = $person->getFullName();
                if ($person->isDeceased()) {
                    $skipped[] = self::skip($personId, null, $name, self::SKIP_DECEASED);
                    continue;
                }
                if (isset($doNotEmailSet[$personId])) {
                    $skipped[] = self::skip($personId, null, $name, self::SKIP_DO_NOT_EMAIL);
                    continue;
                }
                // Person::getEmail() falls back to the family address when the person has none.
                $email = trim((string) $person->getEmail());
                if ($email === '') {
                    $skipped[] = self::skip($personId, null, $name, self::SKIP_NO_EMAIL);
                    continue;
                }
                $key = strtolower($email);
                if (isset($seenAddresses[$key])) {
                    $skipped[] = self::skip($personId, null, $name, self::SKIP_DUPLICATE);
                    continue;
                }
                $seenAddresses[$key] = true;
                $recipients[] = [
                    'personId' => $personId,
                    'familyId' => null,
                    'name'     => $name,
                    'email'    => $email,
                ];
            }
        }

        if ($familyIds !== []) {
            $families = [];
            foreach (FamilyQuery::create()->filterById($familyIds)->find() as $family) {
                $families[(int) $family->getId()] = $family;
            }

            foreach ($familyIds as $familyId) {
                $family = $families[$familyId] ?? null;
                if ($family === null) {
                    $skipped[] = self::skip(null, $familyId, '', self::SKIP_NOT_FOUND);
                    continue;
                }
                $name = sprintf(gettext('%s Family'), $family->getName());
                if ($family->getDateDeactivated() !== null) {
                    $skipped[] = self::skip(null, $familyId, $name, self::SKIP_INACTIVE);
                    continue;
                }
                $email = trim((string) $family->getEmail());
                if ($email === '') {
                    $skipped[] = self::skip(null, $familyId, $name, self::SKIP_NO_EMAIL);
                    continue;
                }
                $key = strtolower($email);
                if (isset($seenAddresses[$key])) {
                    $skipped[] = self::skip(null, $familyId, $name, self::SKIP_DUPLICATE);
                    continue;
                }
                $seenAddresses[$key] = true;
                $recipients[] = [
                    'personId' => null,
                    'familyId' => $familyId,
                    'name'     => $name,
                    'email'    => $email,
                ];
            }
        }

        return ['recipients' => $recipients, 'skipped' => $skipped];
    }

    /**
     * Sends one ComposerEmail per recipient. Never throws for a transport
     * failure: the failed recipient is reported with PHPMailer's error text.
     *
     * @param list<array{personId: ?int, familyId: ?int, name: string, email: string}> $recipients
     *
     * @return array{sent: list<array{personId: ?int, familyId: ?int, name: string, email: string}>, failed: list<array{personId: ?int, familyId: ?int, name: string, email: string, error: string}>}
     */
    public function send(array $recipients, string $subject, string $body, ?User $sentBy = null): array
    {
        $sent = [];
        $failed = [];

        foreach ($recipients as $recipient) {
            $public = [
                'personId' => $recipient['personId'],
                'familyId' => $recipient['familyId'],
                'name'     => $recipient['name'],
                'email'    => $recipient['email'],
            ];
            try {
                $email = new ComposerEmail(
                    $recipient['email'],
                    $recipient['name'],
                    $subject,
                    $body,
                );
                // Attribute the history row to the record and the sending user.
                $email->setLogContext($recipient['personId'], $recipient['familyId'], $sentBy?->getId() !== null ? (int) $sentBy->getId() : null);
                if ($email->send()) {
                    $sent[] = $public;
                } else {
                    $public['error'] = $email->getError() ?: gettext('Email sending is disabled');
                    $failed[] = $public;
                }
            } catch (\Throwable $e) {
                $public['error'] = $e->getMessage();
                $failed[] = $public;
            }
        }

        LoggerUtils::getAppLogger()->info('Composer email sent', [
            'sentBy'  => $sentBy?->getUserName() ?? '',
            'subject' => $subject,
            'sent'    => count($sent),
            'failed'  => count($failed),
        ]);

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * The message as it would be sent to one recipient, without sending it.
     *
     * @param array{personId: ?int, familyId: ?int, name: string, email: string} $recipient
     */
    public function preview(array $recipient, string $subject, string $body): string
    {
        return (new ComposerEmail($recipient['email'], $recipient['name'], $subject, $body))->getHtml();
    }

    /**
     * The closing the composer pre-fills under two blank lines, from the letter settings
     * (sConfirmSincerely / sConfirmSigner); the church name stands in for a missing signer.
     */
    public static function defaultSignature(): string
    {
        $sincerely = trim((string) SystemConfig::getValue('sConfirmSincerely')) ?: gettext('Sincerely');
        $signer = trim((string) SystemConfig::getValue('sConfirmSigner')) ?: ChurchMetaData::getChurchName();

        return $sincerely . ",\n" . $signer;
    }

    /**
     * Casts to positive ints, drops the rest, keeps first-seen order.
     *
     * @return int[]
     */
    public static function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $id = (int) $id;
                if ($id > 0 && !in_array($id, $out, true)) {
                    $out[] = $id;
                }
            }
        }

        return $out;
    }

    /**
     * @return array{personId: ?int, familyId: ?int, name: string, reason: string}
     */
    private static function skip(?int $personId, ?int $familyId, string $name, string $reason): array
    {
        return ['personId' => $personId, 'familyId' => $familyId, 'name' => $name, 'reason' => $reason];
    }
}
