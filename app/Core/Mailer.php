<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Sends multipart/alternative mail through PHP's mail().
 *
 * mail() is enough on shared cPanel hosting, which runs a local MTA. It is not
 * enough at volume, and it has no bounce handling — when transactional volume
 * matters, this class is the one place to swap in an API (Postmark, SES) and
 * nothing else changes.
 *
 * Every message carries a plain-text part as well as HTML. Some people read
 * mail in plain text, some clients strip HTML, and a text part measurably
 * improves the odds of landing in an inbox rather than a spam folder.
 */
final class Mailer
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            (string) Config::get('mail.from_address', 'noreply@fixlisted.com'),
            (string) Config::get('mail.from_name', 'Fix Listed'),
        );
    }

    public function send(
        string $to,
        string $subject,
        string $html,
        string $text,
        ?string $replyTo = null,
    ): bool {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $boundary = 'fl_' . bin2hex(random_bytes(12));

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $this->encodeName($this->fromName) . ' <' . $this->fromAddress . '>',
            'Reply-To: ' . ($replyTo ?: $this->fromAddress),
            'X-Mailer: Fix Listed',
            // Transactional mail should not be auto-replied to, and should not
            // trigger an out-of-office storm.
            'Auto-Submitted: auto-generated',
        ];

        $body = "--{$boundary}\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: 8bit\r\n\r\n"
              . $this->normalise($text) . "\r\n\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: 8bit\r\n\r\n"
              . $html . "\r\n\r\n"
              . "--{$boundary}--\r\n";

        return @mail(
            $to,
            $this->encodeSubject($subject),
            $body,
            implode("\r\n", $headers),
            '-f' . $this->fromAddress,
        );
    }

    /** RFC 2047 encoding, so an em-dash in a subject line is not mangled. */
    private function encodeSubject(string $subject): string
    {
        return preg_match('/[\x80-\xFF]/', $subject) === 1
            ? '=?UTF-8?B?' . base64_encode($subject) . '?='
            : $subject;
    }

    private function encodeName(string $name): string
    {
        return preg_match('/[\x80-\xFF]/', $name) === 1
            ? '=?UTF-8?B?' . base64_encode($name) . '?='
            : '"' . str_replace('"', '', $name) . '"';
    }

    private function normalise(string $text): string
    {
        return str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $text);
    }
}
