<?php
declare(strict_types=1);

namespace FixListed\Core;

use RuntimeException;

/**
 * Sends through Resend's HTTPS API instead of SMTP.
 *
 * Exists because this shared host intercepts outbound SMTP. Connecting to
 * smtp.resend.com:587 from here reaches A2's own mail filter, which answers
 * the STARTTLS upgrade with A2's certificate ("Peer certificate
 * CN=az1-ts106.a2hosting.com did not match expected CN=smtp.resend.com").
 * The handshake cannot succeed: the client is talking to a proxy that has no
 * way to present someone else's certificate. No password, CA bundle or TLS
 * version changes that.
 *
 * Port 443 is not intercepted, because doing so would break every outbound
 * HTTPS request the server makes. Same provider, same account, same API key —
 * a transport the host does not sit in the middle of.
 *
 * The trade-off is that the message is handed over as fields rather than as a
 * raw MIME blob, so Resend assembles the MIME itself. That is fine, and in
 * fact better: it means the parts, boundaries and encodings come from code
 * that sends billions of messages rather than from Mailer.
 */
final class MailApi
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    private string $lastError = '';

    public function __construct(
        private readonly string $apiKey,
        private readonly int $timeout = 15,
    ) {
    }

    public static function fromConfig(): ?self
    {
        if (Config::get('mail.transport') !== 'api') {
            return null;
        }
        $key = (string) Config::get('mail.api.key', '');
        return $key === '' ? null : new self($key);
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    /**
     * @param array<int,string> $to
     */
    public function send(
        string $fromHeader,
        array $to,
        string $subject,
        string $html,
        string $text,
        string $replyTo = '',
    ): bool {
        $payload = [
            'from'    => $fromHeader,
            'to'      => array_values($to),
            'subject' => $subject,
            'html'    => $html,
            'text'    => $text,
        ];
        if ($replyTo !== '') {
            $payload['reply_to'] = $replyTo;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $this->lastError = 'could not encode the message as JSON';
            return false;
        }

        try {
            [$status, $body] = function_exists('curl_init')
                ? $this->postWithCurl($json)
                : $this->postWithStream($json);
        } catch (RuntimeException $e) {
            $this->lastError = $e->getMessage();
            error_log('Mail API send failed: ' . $this->lastError);
            return false;
        }

        if ($status >= 200 && $status < 300) {
            return true;
        }

        // Resend answers failures with {"name":"...","message":"..."}. The
        // message is the part worth reading; the raw body is the fallback.
        $decoded = json_decode($body, true);
        $reason  = is_array($decoded) && isset($decoded['message'])
            ? (string) $decoded['message']
            : trim($body);

        $this->lastError = 'HTTP ' . $status . ($reason !== '' ? ': ' . $reason : '') . $this->hint($status, $reason);
        error_log('Mail API send failed: ' . $this->lastError);
        return false;
    }

    /** @return array{0:int,1:string} */
    private function postWithCurl(string $json): array
    {
        $ch = curl_init(self::ENDPOINT);
        if ($ch === false) {
            throw new RuntimeException('curl could not be initialised');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('could not reach api.resend.com' . ($error !== '' ? ': ' . $error : ''));
        }
        return [$status, (string) $body];
    }

    /**
     * Fallback for a PHP build without the curl extension. Needs
     * allow_url_fopen, which shared hosts normally leave on.
     *
     * @return array{0:int,1:string}
     */
    private function postWithStream(string $json): array
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Authorization: Bearer {$this->apiKey}\r\n"
                                 . "Content-Type: application/json\r\n"
                                 . "Accept: application/json\r\n",
                'content'       => $json,
                'timeout'       => $this->timeout,
                // Without this a 4xx makes file_get_contents return false and
                // throw away the body that says what was wrong.
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents(self::ENDPOINT, false, $context);
        if ($body === false) {
            throw new RuntimeException(
                'could not reach api.resend.com — no curl extension and allow_url_fopen appears to be off'
            );
        }

        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
            }
        }
        return [$status, (string) $body];
    }

    private function hint(int $status, string $reason): string
    {
        if ($status === 401 || $status === 403) {
            return ' — the API key was refused. Create a fresh one at resend.com/api-keys '
                 . 'with Sending access, and re-run php bin/configure.php.';
        }
        if ($status === 422 && stripos($reason, 'domain') !== false) {
            return ' — the from-address is on a domain Resend has not verified yet. '
                 . 'Add fixlisted.com at resend.com/domains and publish the DNS records it gives you.';
        }
        if ($status === 429) {
            return ' — rate limited. This is temporary.';
        }
        return '';
    }
}
