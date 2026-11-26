<?php

namespace App\Contracts;

/**
 * Sends one email. The real implementation uses PHPMailer over SMTP;
 * tests swap in a fake so no real email is ever sent from a test.
 */
interface MailTransport
{
    /**
     * Whether SMTP settings are present.
     */
    public function isConfigured(): bool;

    /**
     * @throws \RuntimeException when the message could not be sent
     */
    public function send(string $toEmail, string $toName, string $subject, string $html, string $text): void;
}
