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
 * Resolves composer recipients from person / family ids and queues one
 * ComposerEmail per recipient (EmailQueueService sends them).
 *
 * The API never accepts raw addresses: the server owns the recipient list, so
 * it can apply the do-not-email property, skip the deceased and the
 * address-less, deduplicate, and report every skipped recipient by name.
 * A family recipient is mailed at Family::getEmailRecipient()'s address.
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
    public const SKIP_NO_ADULT_EMAIL = 'no-adult-email';

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
                $email = trim((string) $family->getEmailRecipient()?->getEmail());
                if ($email === '') {
                    $skipped[] = self::skip(null, $familyId, $name, self::SKIP_NO_ADULT_EMAIL);
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
     * Queues one message per recipient for EmailQueueService to send. The message is
     * rendered once, now, so every recipient gets what the author previewed.
     *
     * @param non-empty-list<array{personId: ?int, familyId: ?int, name: string, email: string}> $recipients
     *
     * @return string the batch id
     */
    public function queue(array $recipients, string $subject, string $body, ?User $sentBy = null): string
    {
        $batch = (new EmailQueueService())->enqueue(
            $recipients,
            $subject,
            $this->preview($recipients[0], $subject, $body),
            $sentBy?->getId() !== null ? (int) $sentBy->getId() : null,
        );

        LoggerUtils::getAppLogger()->info('Composer email queued', [
            'sentBy'  => $sentBy?->getUserName() ?? '',
            'subject' => $subject,
            'batch'   => $batch,
            'queued'  => count($recipients),
        ]);

        return $batch;
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
     * The closing the composer pre-fills under two blank lines:
     *   Sincerely,
     *   <full name of the user writing the message>
     *   <church name>
     * The first word comes from sConfirmSincerely; the report signer setting is not used
     * because the message is from the person writing it, not from the office.
     */
    public static function defaultSignature(?User $user): string
    {
        $sincerely = trim((string) SystemConfig::getValue('sConfirmSincerely')) ?: gettext('Sincerely');
        $name = $user !== null ? trim($user->getFullName()) ?: (string) $user->getUserName() : '';
        $lines = array_filter([$sincerely . ',', $name, ChurchMetaData::getChurchName()], static fn (string $l): bool => $l !== '');

        return implode("\n", $lines);
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
