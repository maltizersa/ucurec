<?php

namespace Tests\Support;

use App\Contracts\MailTransport;
use RuntimeException;

/**
 * Records messages instead of sending them.
 */
class FakeMailTransport implements MailTransport
{
    /** @var list<array{to: string, name: string, subject: string, html: string, text: string}> */
    public array $sent = [];

    public function __construct(public bool $configured = true, public bool $fail = false) {}

    public function isConfigured(): bool
    {
        return $this->configured;
    }

    public function send(string $toEmail, string $toName, string $subject, string $html, string $text): void
    {
        if ($this->fail) {
            throw new RuntimeException('Email could not be sent: SMTP Error: Could not connect to SMTP host.');
        }

        $this->sent[] = ['to' => $toEmail, 'name' => $toName, 'subject' => $subject, 'html' => $html, 'text' => $text];
    }
}
