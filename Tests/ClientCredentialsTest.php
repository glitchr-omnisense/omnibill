<?php

namespace Omnibill\Tests;

use Omnibill\Exception\InvalidKeyException;
use Omnibill\Exception\UnreachableException;
use Omnibill\Http\ClientCredentials;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ClientCredentialsTest extends TestCase
{
    public function testATokenIsAskedOnceAndAgainAMinuteBeforeItLapses(): void
    {
        $now = 1_000_000;
        $calls = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls): MockResponse {
            parse_str((string) $options['body'], $body);
            $calls[] = $body;

            return new MockResponse(json_encode(['access_token' => 't'.\count($calls), 'token_type' => 'Bearer', 'expires_in' => 3600]));
        });
        $credentials = new ClientCredentials($http, 'p', 'https://auth.example/token', 'id', 'secret', 'openid', static function () use (&$now): int { return $now; });

        self::assertSame('t1', $credentials->token());
        self::assertSame('t1', $credentials->token());
        self::assertSame([['grant_type' => 'client_credentials', 'client_id' => 'id', 'client_secret' => 'secret', 'scope' => 'openid']], $calls);
        $now += 3541;
        self::assertSame('t2', $credentials->token());
        $credentials->reset();
        self::assertSame('t3', $credentials->token());
    }

    public function testRefusedCredentialsAreTheSitesErrorAndSilenceIsUnreachable(): void
    {
        try {
            (new ClientCredentials(new MockHttpClient(new MockResponse('{"error":"invalid_client","error_description":"Bad client credentials"}', ['http_code' => 401])), 'p', 'https://auth.example/token', 'id', 'bad'))->token();
            self::fail();
        } catch (InvalidKeyException $e) {
            self::assertSame('[p] The client credentials were refused (HTTP 401): Bad client credentials', $e->getMessage());
        }
        $this->expectException(UnreachableException::class);
        (new ClientCredentials(new MockHttpClient(new MockResponse('', ['http_code' => 503])), 'p', 'https://auth.example/token', 'id', 's'))->token();
    }
}
