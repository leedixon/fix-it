<?php
declare(strict_types=1);

namespace FixListed\Core;

/**
 * Sends multipart/alternative mail through one of three transports: the
 * provider's HTTPS API, authenticated SMTP, or PHP's mail().
 *
 * The API is preferred on this install, because the host intercepts outbound
 * SMTP — see MailApi for the certificate mismatch that proves it. SMTP remains
 * supported for a host that leaves port 587 alone.
 *
 * Either beats mail(). Mail sent by the web server claiming to come
 * from a domain hosted elsewhere fails SPF and DKIM, and a domain with a DMARC
 * policy has those messages rejected outright — with no bounce and nothing in
 * the spam folder, so it looks exactly like the code never ran. Sending
 * through the domain's real provider makes the message authentic rather than
 * merely claiming to be.
 *
 * mail() remains the fallback for a domain whose mail is hosted on the same
 * server, where it is authenticated by virtue of being local.
 *
 * Every message carries a plain-text part as well as HTML: some people read
 * mail that way, some clients strip HTML, and its absence is itself a spam
 * signal.
 */
final class Mailer
{
    public function __construct(
        private readonly string $fromAddress,
        private readonly string $fromName,
        private readonly ?Smtp $smtp = null,
        private readonly ?MailApi $api = null,
        // Where replies go, when it differs from the sending address. The
        // brand sends from hello@fixlisted.com; a person reads the replies
        // somewhere else. Without this, replying to a transactional email
        // reaches an address nobody watches.
        private readonly string $replyToDefault = '',
    ) {
    }

    public static function fromConfig(): self
    {
        $from = (string) Config::get('mail.from_address', 'noreply@fixlisted.com');
        return new self(
            $from,
            (string) Config::get('mail.from_name', 'Fix Listed'),
            Smtp::fromConfig(),
            MailApi::fromConfig(),
            (string) Config::get('mail.reply_to', $from),
        );
    }

    public function transport(): string
    {
        if ($this->api !== null) {
            return 'api (https)';
        }
        return $this->smtp !== null ? 'smtp' : 'mail()';
    }

    /** Why the last send failed, when the transport can say. */
    public function lastError(): string
    {
        return $this->api?->lastError() ?? '';
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

        // The API takes the message as fields and builds the MIME itself, so
        // none of the assembly below applies to it.
        if ($this->api !== null) {
            return $this->api->send(
                $this->encodeName($this->fromName) . ' <' . $this->fromAddress . '>',
                [$to],
                $subject,
                $html,
                $text,
                $replyTo ?: ($this->replyToDefault ?: $this->fromAddress),
            );
        }

        $boundary = 'fl_' . bin2hex(random_bytes(12));
        $body     = $this->body($boundary, $html, $text);
        $headers  = $this->headers($boundary, $replyTo);

        if ($this->smtp !== null) {
            // SMTP sends the whole message, so the envelope headers have to be
            // in the data. mail() adds these itself and would duplicate them.
            $raw = 'To: ' . $to . "\r\n"
                 . 'Subject: ' . $this->encodeSubject($subject) . "\r\n"
                 . 'Date: ' . date('r') . "\r\n"
                 . 'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $this->domain() . ">\r\n"
                 . implode("\r\n", $headers) . "\r\n\r\n"
                 . $body;

            return $this->smtp->send($this->fromAddress, $to, $raw);
        }

        return @mail(
            $to,
            $this->encodeSubject($subject),
            $body,
            implode("\r\n", $headers),
            '-f' . $this->fromAddress,
        );
    }

    /** @return array<int,string> */
    private function headers(string $boundary, ?string $replyTo): array
    {
        return [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'From: ' . $this->encodeName($this->fromName) . ' <' . $this->fromAddress . '>',
            'Reply-To: ' . ($replyTo ?: ($this->replyToDefault ?: $this->fromAddress)),
            'X-Mailer: Fix Listed',
            // Transactional mail should not trigger an out-of-office reply.
            'Auto-Submitted: auto-generated',
        ];
    }

    private function body(string $boundary, string $html, string $text): string
    {
        return "--{$boundary}\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n\r\n"
             . $this->normalise($text) . "\r\n\r\n"
             . "--{$boundary}\r\n"
             . "Content-Type: text/html; charset=UTF-8\r\n"
             . "Content-Transfer-Encoding: 8bit\r\n\r\n"
             . $this->normalise($html) . "\r\n\r\n"
             . "--{$boundary}--\r\n";
    }

    private function domain(): string
    {
        $parts = explode('@', $this->fromAddress);
        return $parts[1] ?? 'localhost';
    }

    /** RFC 2047, so an em-dash in a subject line is not mangled. */
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
