<?php

namespace Omnibill\Http;

use Omnibill\Exception\OutcomeUnknownException;
use Omnibill\Exception\ProviderException;
use Omnibill\Exception\UnreachableException;
use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * A provider's answer, read whole: its status, headers (lower-case names)
 * and body. send() is the one place the family's gateways call through:
 * no connection, a timeout, a server error or a quota exceeded is an
 * UnreachableException - the provider said nothing about the request - and
 * anything else comes back for the gateway to read. The request's secrets
 * stay out of what it throws: the HTTP client's message with the key's
 * parameters masked (Redaction::text(), api_key=***), its exception wrapped
 * as a copy (Redaction::copy()), no trace keeping the arguments where the
 * options stood.
 *
 * Given the provider's Backoff (a factory's Config::backoff()), send() waits
 * first - no request while the provider asked to wait - and reads every
 * answer's Retry-After and RateLimit fields: a 429 is then a
 * UsageLimitReachedException with the wait it asked for, and the next calls
 * are held until it is over. Without one, a 429 stays an UnreachableException.
 *
 * Since 1.2 a write is told apart: for a request that writes (POST, PUT,
 * PATCH, DELETE by default; $write says it where the method does not - a
 * search sent as a POST is no write, an RPC call that creates is one), a
 * failure after the request may have left - a timeout, a connection lost
 * midway, a 5xx - is an OutcomeUnknownException (an UnreachableException
 * still): the provider may have taken it. A write that never left (the
 * host did not resolve, the connection was refused, the TLS handshake
 * failed) stays a plain UnreachableException, and a 429 too (refused
 * before it was processed).
 */
final readonly class Answer
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        private string $provider,
    ) {
    }

    /** The methods that write, when the caller does not say. */
    public const WRITES = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * What a transport error says when the request cannot have left: no
     * name, no connection, no TLS session (curl's and PHP's stream messages).
     */
    private const NEVER_LEFT = '~resolve host|could not resolve|getaddrinfo|name or service not known|failed to connect|couldn\'t connect|connection refused|ssl connect error|ssl certificate|unable to connect~i';

    /**
     * @param array<string, mixed> $options the HTTP client's options: body, json, query, headers, timeout
     * @param bool|null            $write   whether the request writes; null: by its method (WRITES)
     */
    public static function send(HttpClientInterface $http, string $provider, string $method, string $url, array $options = [], ?Backoff $backoff = null, ?bool $write = null): self
    {
        $write ??= \in_array(strtoupper($method), self::WRITES, true);
        $backoff?->clear();
        try {
            $response = $http->request($method, $url, $options + ['timeout' => 10]);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $body = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            $secrets = Redaction::secrets($url, $options);
            $message = Redaction::text($e->getMessage(), $secrets);
            if ($write && !preg_match(self::NEVER_LEFT, $e->getMessage())) {
                throw Redaction::withoutArguments(new OutcomeUnknownException($provider, 'No answer to a write that may have been taken: '.$message, null, Redaction::copy($e, $secrets)));
            }

            throw Redaction::withoutArguments(new UnreachableException($provider, 'No answer: '.$message, null, Redaction::copy($e, $secrets)));
        }
        $backoff?->observe($status, $headers);
        if ($status >= 500 && $write) {
            throw Redaction::withoutArguments(new OutcomeUnknownException($provider, \sprintf('HTTP %d to a write that may have been taken: %s', $status, mb_substr(trim($body), 0, 200)), (string) $status));
        }
        if ($status >= 500 || 429 === $status) {
            throw Redaction::withoutArguments(new UnreachableException($provider, \sprintf('HTTP %d: %s', $status, mb_substr(trim($body), 0, 200)), (string) $status));
        }

        return new self($status, $headers, $body, $provider);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * The body as a JSON object.
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $data = json_decode($this->body, true);
        if (!\is_array($data)) {
            throw new ProviderException($this->provider, \sprintf('HTTP %d, not a JSON object: %s', $this->status, mb_substr(trim($this->body), 0, 200)));
        }

        $object = [];
        foreach ($data as $key => $value) {
            $object[(string) $key] = $value;
        }

        return $object;
    }
}
