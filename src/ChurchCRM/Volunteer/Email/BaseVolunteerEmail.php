<?php

namespace ChurchCRM\Volunteer\Email;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Emails\BaseEmail;

/**
 * Shared plumbing for the seven Volunteer v2 message types (#9710, design
 * Appendix C).
 *
 * This is the `users/BaseUserEmail.php` shape, for the same reason: subject
 * assembly, `isHTML`, `msgHTML(buildMessage())` and the `toName` / `body`
 * tokens are identical for every message in the module, and seven copies of
 * them is seven places for one of them to drift. Each concrete class is then
 * only its subject, its body prose, its CTA URL and its button text — exactly
 * the four columns Appendix C's table specifies.
 *
 * **It deliberately adds no mail-routing behaviour.** `Reply-To` lives on
 * `BaseEmail` (CR6 / #9733), where every module benefits, and is applied by the
 * drain — never here, and never by a subclass. Appendix C is explicit that a
 * V2 PR must not work around the capability's absence with a Volunteer-only
 * mail base, and this class does not: it overrides nothing `BaseEmail` does
 * about sending, and `getTemplateName()` stays the inherited single template.
 */
abstract class BaseVolunteerEmail extends BaseEmail
{
    protected VolunteerEmailContext $context;

    protected string $recipientName;

    /**
     * @param string[] $toAddresses
     */
    public function __construct(array $toAddresses, string $recipientName, VolunteerEmailContext $context)
    {
        // Assign before parent::__construct(): it is the parent that creates the
        // PHPMailer and the Twig environment, and buildMessage() below reads
        // both of these properties through getTokens().
        $this->recipientName = $recipientName;
        $this->context = $context;

        parent::__construct($toAddresses);

        $this->mail->Subject = SystemConfig::getValue('sChurchName') . ': ' . $this->getSubSubject();
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    /** The message-specific half of the subject line. */
    abstract protected function getSubSubject(): string;

    /**
     * The prose body: what happened, the context block, and one sentence saying
     * what the recipient should do next (Appendix C).
     */
    abstract protected function buildMessageBody(): string;

    public function getTokens(): array
    {
        return array_merge($this->getCommonTokens(), [
            'toName' => $this->recipientName,
            'body' => $this->buildMessageBody(),
        ]);
    }

    protected function getPreheader(): string
    {
        return $this->getSubSubject();
    }

    /**
     * Keep the rendered body in the email history (review, 2026-09-18). Safe here
     * because no volunteer message carries a password, a one-time token or a
     * one-click action link: every one of them says "log in to the portal". So a
     * coordinator asking "what did we actually send them?" can read it on the
     * person's Email History instead of hunting the mail server.
     */
    protected function logsBody(): bool
    {
        return true;
    }

    /**
     * Glue the three parts of a body together with blank lines between them.
     *
     * The shared template renders the body through `{{body|nl2br}}`, so
     * paragraphs are plain newlines — Appendix C's "prefer prose over a table".
     */
    protected function composeBody(string $opening, string $nextStep): string
    {
        return $opening . "\n\n" . $this->context->describe() . "\n\n" . $nextStep;
    }
}
