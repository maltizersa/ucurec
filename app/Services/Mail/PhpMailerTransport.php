<?php

namespace App\Services\Mail;

use App\Contracts\MailTransport;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use RuntimeException;

/**
 * SMTP email through PHPMailer. Settings: config('ucurec.mail'), from the
 * MAIL_* values in .env.
 */
class PhpMailerTransport implements MailTransport
{
    public function isConfigured(): bool
    {
        $config = config('ucurec.mail');

        return $config['enabled']
            && filled($config['host'])
            && filled($config['from_address']);
    }

    public function send(string $toEmail, string $toName, string $subject, string $html, string $text): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Email is not set up (MAIL_MAILER=smtp, MAIL_HOST and MAIL_FROM_ADDRESS are required).');
        }

        $config = config('ucurec.mail');
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $config['host'];
            $mail->Port = $config['port'];
            $mail->Timeout = $config['timeout'];
            // Never print the SMTP conversation: it can contain credentials.
            $mail->SMTPDebug = SMTP::DEBUG_OFF;

            if (filled($config['username'])) {
                $mail->SMTPAuth = true;
                $mail->Username = $config['username'];
                $mail->Password = (string) $config['password'];
            }

            match (strtolower((string) $config['encryption'])) {
                'ssl', 'smtps' => $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS,
                'none', '' => $mail->SMTPAutoTLS = false,
                default => $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS,
            };

            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->setFrom($config['from_address'], $config['from_name']);
            $mail->addAddress($toEmail, $toName);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text;

            $mail->send();
        } catch (PHPMailerException $e) {
            // PHPMailer's ErrorInfo describes the SMTP problem without the message body.
            throw new RuntimeException('Email could not be sent: '.($mail->ErrorInfo ?: $e->getMessage()), previous: $e);
        }
    }
}
