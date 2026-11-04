<?php

namespace ChurchCRM\Emails\signin;

use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\Emails\BaseEmail;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\Utils\DateTimeUtils;
use DateTimeInterface;

/**
 * Tells a person's previous address that their sign-in email changed, or that
 * they asked to change it in the portal (member sign-in design §9). When the
 * previous address is the one the person proved by signing in, a change made
 * by someone else carries the link that confirms the new address.
 */
final class EmailChangeNoticeEmail extends BaseEmail
{
    public const BY_MEMBER = 'member';
    public const BY_SOMEONE_ELSE = 'someone_else';

    public function __construct(
        string $toAddress,
        private readonly Person $person,
        private readonly string $oldEmail,
        private readonly string $newEmail,
        private readonly string $by,
        private readonly ?string $confirmUrl = null,
        private readonly ?DateTimeInterface $linkExpiresAt = null,
    ) {
        parent::__construct([$toAddress]);
        $this->setLogContext((int) $person->getId());
        $this->mail->Subject = ChurchMetaData::getChurchName() . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    private function getSubSubject(): string
    {
        return $this->by === self::BY_MEMBER
            ? gettext('A change of your email address was requested')
            : gettext('Your email address was changed');
    }

    public function getTokens(): array
    {
        return array_merge($this->getCommonTokens(), [
            'toName' => (string) $this->person->getFirstName(),
            'body' => $this->buildBody(),
        ]);
    }

    private function buildBody(): string
    {
        $church = ChurchMetaData::getChurchName();
        if ($this->by === self::BY_MEMBER) {
            return sprintf(
                gettext('A request was made in the Member Portal to change your email address at %1$s from %2$s to %3$s. The change happens only when the link sent to %3$s is used.'),
                $church,
                $this->oldEmail,
                $this->newEmail
            ) . "\n\n" . gettext("If you didn't ask for this, please contact the church office.");
        }

        if ($this->newEmail === '') {
            $body = sprintf(
                gettext('The email address %2$s was removed from your record at %1$s. You have been signed out of the Member Portal on every device.'),
                $church,
                $this->oldEmail
            );
        } else {
            $body = sprintf(
                gettext('The email address on your record at %1$s was changed from %2$s to %3$s. You have been signed out of the Member Portal on every device.'),
                $church,
                $this->oldEmail,
                $this->newEmail
            );
            $body .= "\n\n" . ($this->confirmUrl !== null
                ? sprintf(
                    gettext('If %1$s is your new address, use the button below to confirm it. Until it is confirmed, it cannot be used to sign in. The link works once, until %2$s.'),
                    $this->newEmail,
                    DateTimeUtils::formatDate($this->linkExpiresAt, true)
                )
                : gettext('Until the church office confirms it, the new address cannot be used to sign in.'));
        }

        return $body . "\n\n" . gettext("If you didn't ask for this change, please contact the church office.");
    }

    protected function getFullURL(): string
    {
        return $this->confirmUrl ?? '';
    }

    protected function getButtonText(): string
    {
        return gettext('Confirm Email Address');
    }

    protected function getPreheader(): string
    {
        return $this->getSubSubject();
    }

    protected function getLogKind(): string
    {
        return 'signin.email-notice';
    }
}
