<?php

namespace ChurchCRM\Emails;

/**
 * One message written by a user in the in-app email composer, addressed to a
 * single recipient. The composer service instantiates one of these per
 * resolved recipient so every person gets their own copy (their own To:
 * header, their own greeting) instead of one BCC blast.
 *
 * The subject and body arrive already sanitized by InputSanitizationMiddleware
 * ('text' mode: tags stripped); the template escapes them again on output and
 * turns newlines into <br>. The author writes the greeting and closing (the composer
 * pre-fills the closing from sConfirmSincerely / sConfirmSigner); nothing is generated
 * around the body except the church header.
 */
class ComposerEmail extends BaseEmail
{
    public function __construct(
        string $toAddress,
        string $toName,
        string $subject,
        private readonly string $body,
    ) {
        parent::__construct([]);
        $this->mail->addAddress($toAddress, $toName);
        $this->mail->Subject = $subject;
        $this->mail->isHTML(true);
        $this->mail->msgHTML($this->buildMessage());
    }

    public function getTokens(): array
    {
        return array_merge($this->getCommonTokens(), [
            'toName' => '',
            'body'   => $this->body,
        ]);
    }

    protected function getLogKind(): string
    {
        return 'composer';
    }

    /** The composer body is the one message a member should be able to read back later. */
    protected function logsBody(): bool
    {
        return true;
    }

    /** The composer template renders the body only: no generated greeting, closing or footer. */
    protected function getTemplateName(): string
    {
        return 'ComposerEmail.html.twig';
    }

    /** The rendered HTML exactly as it will be sent, for the composer's Preview. */
    public function getHtml(): string
    {
        return (string) $this->mail->Body;
    }

    protected function getFullURL(): string
    {
        return '';
    }

    protected function getButtonText(): string
    {
        return '';
    }

    protected function getPreheader(): string
    {
        $firstLine = trim((string) strtok($this->body, "\n"));

        return mb_strlen($firstLine) > 100 ? mb_substr($firstLine, 0, 97) . '…' : $firstLine;
    }
}
