<?php

namespace Omnibill\Http;

use Omnibill\Exception\InvalidKeyException;
use Omnisense\Contracts\Policy\Backoff;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * An OAuth 2.0 access token by the client credentials grant - what the
 * platforms' APIs ask (XP Z12-013's, PISTE's): asked once, kept until a
 * minute before it lapses. Given the provider's Backoff, the token
 * endpoint's 429 and Retry-After hold the provider's calls as any other's.
 */
final class ClientCredentials
{
    private ?string $token = null;
    private int $expires = 0;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string $provider,
        private readonly string $tokenUrl,
        private readonly string $clientId,
        #[\SensitiveParameter] private readonly string $clientSecret,
        private readonly ?string $scope = null,
        private readonly ?\Closure $clock = null,
        private readonly ?Backoff $backoff = null,
    ) {
    }

    /** @phpstan-impure asked again once the token lapses */
    public function token(): string
    {
        $now = $this->now();
        if (null !== $this->token && $now < $this->expires) {
            return $this->token;
        }
        $answer = Answer::send($this->http, $this->provider, 'POST', $this->tokenUrl, ['body' => array_filter([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $this->scope,
        ])], $this->backoff, false);
        if (\in_array($answer->status, [400, 401, 403], true)) {
            $error = json_decode($answer->body, true);
            $code = \is_array($error) && \is_scalar($error['error'] ?? null) ? (string) $error['error'] : null;
            $reason = \is_array($error) ? ($error['error_description'] ?? $code ?? 'no reason given') : mb_substr(trim($answer->body), 0, 200);
            throw new InvalidKeyException($this->provider, \sprintf('The client credentials were refused (HTTP %d): %s', $answer->status, \is_scalar($reason) ? (string) $reason : 'no reason given'), $code);
        }
        $data = $answer->json();
        $token = $data['access_token'] ?? null;
        $this->token = \is_scalar($token) ? (string) $token : throw new InvalidKeyException($this->provider, 'The token endpoint gave no access_token.');
        $this->expires = $now + max(0, (is_numeric($expires = $data['expires_in'] ?? 3600) ? (int) $expires : 3600) - 60);

        return $this->token;
    }

    /** Forget the token: the next call asks for a new one (after a 401). */
    public function reset(): void
    {
        $this->token = null;
    }

    private function now(): int
    {
        $now = null === $this->clock ? null : ($this->clock)();

        return is_numeric($now) ? (int) $now : time();
    }
}
