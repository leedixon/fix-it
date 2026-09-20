<?php
declare(strict_types=1);

namespace FixListed\Core;

use RuntimeException;

/**
 * Minimal authenticated SMTP client.
 *
 * Exists so mail can be sent through the domain's real mail provider rather
 * than through the web server. A message sent from the web host claiming to
 * come from a Google Workspace domain fails SPF and DKIM, and a domain with a
 * DMARC policy then has that message rejected outright — silently, with no
 * bounce and nothing in the spam folder. Sending through the provider makes
 * the message genuinely authentic instead of merely claiming to be.
 *
 * Hand-rolled rather than pulled from Composer: this is a shared-hosting
 * install with no dependency manager, and the protocol needed here is small.
 */
final class Smtp
{
    /** @var resource|null */
    private $socket = null;
    private string $lastResponse = '';

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $encryption = 'tls',   // tls | ssl | none
        private readonly int $timeout = 20,
    ) {
    }

    public static function fromConfig(): ?self
    {
        if (Config::get('mail.transport') !== 'smtp') {
            return null;
        }
        $host = (string) Config::get('mail.smtp.host', '');
        $user = (string) Config::get('mail.smtp.username', '');
        $pass = (string) Config::get('mail.smtp.password', '');
        if ($host === '' || $user === '' || $pass === '') {
            return null;
        }
        return new self(
            $host,
            (int) Config::get('mail.smtp.port', 587),
            $user,
            $pass,
            (string) Config::get('mail.smtp.encryption', 'tls'),
        );
    }

    /**
     * @param string $envelopeFrom the bounce address, not the From: header
     * @param string $rawMessage   headers and body, already assembled
     */
    public function send(string $envelopeFrom, string $recipient, string $rawMessage): bool
    {
        try {
            $this->connect();
            $this->command('MAIL FROM:<' . $envelopeFrom . '>', 250);
            $this->command('RCPT TO:<' . $recipient . '>', 250);
            $this->command('DATA', 354);
            $this->write($this->prepareBody($rawMessage) . "\r\n.\r\n");
            $this->expect(250);
            $this->command('QUIT', 221);
            return true;
        } catch (\Throwable $e) {
            error_log('SMTP send failed: ' . $e->getMessage() . ' | last response: ' . $this->lastResponse);
            return false;
        } finally {
            $this->close();
        }
    }

    /** Opens the connection and authenticates. Throws with a readable reason. */
    private function connect(): void
    {
        $scheme = $this->encryption === 'ssl' ? 'ssl://' : '';
        $socket = @stream_socket_client(
            $scheme . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
        );
        if ($socket === false) {
            throw new RuntimeException("cannot reach {$this->host}:{$this->port} ({$errstr})");
        }
        $this->socket = $socket;
        stream_set_timeout($this->socket, $this->timeout);

        $this->expect(220);
        $this->command('EHLO ' . $this->heloName(), 250);

        if ($this->encryption === 'tls') {
            $this->command('STARTTLS', 220);
            $this->startTls();
            // The server's capabilities can change after the upgrade, so the
            // handshake starts again on the encrypted channel.
            $this->command('EHLO ' . $this->heloName(), 250);
        }

        $this->command('AUTH LOGIN', 334);
        $this->command(base64_encode($this->username), 334);
        // 235 is "authenticated". A 535 here means the password was refused —
        // with Google that usually means an ordinary password was used where
        // an App Password is required.
        $this->command(base64_encode($this->password), 235);
    }

    /**
     * Upgrades the plain connection to TLS.
     *
     * STREAM_CRYPTO_METHOD_TLS_CLIENT does not include TLS 1.3, so a server
     * offering only 1.3 fails against that constant alone — the newer methods
     * are added here when the PHP build defines them.
     *
     * peer_name is set explicitly because the certificate is verified against
     * the hostname, and SNI decides which certificate a shared relay presents
     * in the first place.
     */
    private function startTls(): void
    {
        stream_context_set_option($this->socket, 'ssl', 'peer_name', $this->host);
        stream_context_set_option($this->socket, 'ssl', 'SNI_enabled', true);

        $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        foreach (['STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT', 'STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT'] as $constant) {
            if (defined($constant)) {
                $method |= constant($constant);
            }
        }

        error_clear_last();
        if (@stream_socket_enable_crypto($this->socket, true, $method)) {
            return;
        }

        // Without this detail the failure is unfixable: "negotiation failed"
        // covers an expired certificate, a missing CA bundle and a protocol
        // mismatch equally, and they need different fixes. OpenSSL's own
        // message names which one it is.
        $last   = error_get_last();
        $detail = $last !== null ? trim(strip_tags($last['message'])) : '';
        $hint   = '';
        if (str_contains($detail, 'certificate verify failed')) {
            $hint = ' — this server cannot verify the certificate chain, usually a missing or '
                  . 'stale CA bundle. Check openssl.cafile in php.ini, or ask the host to update ca-certificates.';
        } elseif (str_contains($detail, 'protocol') || str_contains($detail, 'version')) {
            $hint = ' — TLS version mismatch between this PHP build and the server.';
        }

        throw new RuntimeException(
            'STARTTLS negotiation failed' . ($detail !== '' ? ': ' . $detail : ' (OpenSSL gave no detail)') . $hint
        );
    }

    private function heloName(): string
    {
        $host = parse_url((string) Config::get('app.url', ''), PHP_URL_HOST);
        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function command(string $line, int $expected): void
    {
        $this->write($line . "\r\n");
        $this->expect($expected);
    }

    private function write(string $data): void
    {
        if ($this->socket === null || fwrite($this->socket, $data) === false) {
            throw new RuntimeException('connection closed while writing');
        }
    }

    private function expect(int $code): void
    {
        $response = $this->readResponse();
        if ((int) substr($response, 0, 3) !== $code) {
            throw new RuntimeException("expected {$code}, got: " . trim($response));
        }
    }

    /** Reads a reply, following multi-line continuations ("250-" before "250 "). */
    private function readResponse(): string
    {
        $response = '';
        while ($this->socket !== null && ($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        if ($response === '') {
            throw new RuntimeException('no response from server');
        }
        return $this->lastResponse = $response;
    }

    /**
     * Normalises line endings to CRLF and dot-stuffs, because a line
     * consisting of a single dot would otherwise end the message early.
     */
    private function prepareBody(string $message): string
    {
        $message = str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $message);
        return preg_replace('/^\./m', '..', $message) ?? $message;
    }

    private function close(): void
    {
        if ($this->socket !== null) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
