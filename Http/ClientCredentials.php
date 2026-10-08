<?php

namespace Omnibill\Http;

use Omnibill\Exception\InvalidKeyException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * An OAuth 2.0 access token by the client credentials grant - what the
 * platforms' APIs ask (XP Z12-013's, PISTE's): asked once, kept until a
 * minute before it lapses.
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
    ) {
    }

    public function token(): string
    {
        $now = $this->clock ? ($this->clock)() : time();
        if (null !== $this->token && $now < $this->expires) {
            return $this->token;
        }
        $answer = Answer::send($this->http, $this->provider, 'POST', $this->tokenUrl, ['body' => array_filter([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $this->scope,
        ])]);
        if (\in_array($answer->status, [400, 401, 403], true)) {
            $error = json_decode($answer->body, true);
            throw new InvalidKeyException($this->provider, \sprintf('The client credentials were refused (HTTP %d): %s', $answer->status, \is_array($error) ? ($error['error_description'] ?? $error['error'] ?? 'no reason given') : mb_substr(trim($answer->body), 0, 200)), \is_array($error) ? ($error['error'] ?? null) : null);
        }
        $data = $answer->json();
        $this->token = (string) ($data['access_token'] ?? throw new InvalidKeyException($this->provider, 'The token endpoint gave no access_token.'));
        $this->expires = $now + max(0, (int) ($data['expires_in'] ?? 3600) - 60);

        return $this->token;
    }

    /** Forget the token: the next call asks for a new one (after a 401). */
    public function reset(): void
    {
        $this->token = null;
    }
}
