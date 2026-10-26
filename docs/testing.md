---
title: Testing
order: 60
---

# Testing

An application that uses Omnibill tests its own code, not the platforms: the platform gateways take
the HTTP client they call with, and the e-mail gateway takes the mailer and the status store, so a
test hands them a `MockHttpClient`, a mailer that keeps what it is given, an `InMemoryStatusStore`,
and checks what was sent.

Omnibill ships no fake gateway and no conformance suite: there is no `Testing\` namespace in the
package. `Omnibill\Email\InMemoryStatusStore` is the one test helper shipped, and it is a real
store (this process's memory).

## A test case

```php
use Omnibill\Afnor\AfnorGatewayFactory;
use Omnibill\Email\EmailGatewayFactory;
use Omnibill\Exception\UnreachableException;
use Omnibill\Model\FlowState;
use Omnibill\Model\Invoice;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\Party;
use Omnibill\Model\Syntax;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class InvoicingTest extends TestCase
{
    public function testAnInvoiceGoesToThePlatformAsAFlow(): void
    {
        $upload = new MockResponse('{"flowId":"f-1","submittedAt":"2026-10-26T09:00:00Z"}', ['http_code' => 202]);
        $http = new MockHttpClient([new MockResponse('{"access_token":"t","expires_in":3600}'), $upload]);
        $platform = (new AfnorGatewayFactory($http))->create(['flow_url' => 'https://pa.example/flow-service', 'token_url' => 'https://pa.example/token', 'client_id' => 'c', 'client_secret' => 's', 'rule' => 'B2B']);

        $flow = $platform->submit(new Invoice('%PDF-1.7', 'F-1.pdf', Syntax::FACTURX, 'F-1'), 'order-1');

        self::assertSame('f-1', $flow->reference);
        self::assertSame(FlowState::PENDING, $flow->state);
        self::assertSame('https://pa.example/flow-service/v1/flows', $upload->getRequestUrl());
        self::assertStringContainsString('"processingRule":"B2B","flowSyntax":"Factur-X"', $upload->getRequestOptions()['body']);
        self::assertContains('Authorization: Bearer t', $upload->getRequestOptions()['headers']);
    }

    public function testAPlatformDownIsUnreachable(): void
    {
        $http = new MockHttpClient([new MockResponse('{"access_token":"t"}'), new MockResponse('', ['http_code' => 503])]);
        $platform = (new AfnorGatewayFactory($http))->create(['flow_url' => 'https://pa.example/flow-service', 'token_url' => 'https://pa.example/token', 'client_id' => 'c', 'client_secret' => 's']);

        $this->expectException(UnreachableException::class);
        $platform->submit(new Invoice('%PDF-1.7', 'F-1.pdf', Syntax::FACTURX, 'F-1'));
    }

    public function testAnInvoiceByEmailIsSent(): void
    {
        $mailer = new class implements MailerInterface {
            /** @var list<Email> */
            public array $sent = [];

            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                $this->sent[] = $message;
            }
        };
        $gateway = (new EmailGatewayFactory($mailer))->create(['from' => 'factures@atelier.example']);

        $flow = $gateway->submit(new Invoice('%PDF-1.7', 'F-1.pdf', Syntax::FACTURX, 'F-1', buyer: new Party('Érable', email: 'compta@erable.example')));

        self::assertSame(LifecycleStatus::SENT, $flow->status);
        self::assertSame('Facture F-1', $mailer->sent[0]->getSubject());
        self::assertSame('F-1.pdf', $mailer->sent[0]->getAttachments()[0]->getFilename());
    }
}
```

```
$ vendor/bin/phpunit --bootstrap vendor/autoload.php InvoicingTest.php
PHPUnit 11.5.57 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.27

...                                                                 3 / 3 (100%)

Time: 00:00.078, Memory: 10.00 MB

OK (3 tests, 9 assertions)
```

(run on 2026-10-26 on the packages' checkouts, no network)

- A platform gateway asks its token first: the first answer of the `MockHttpClient`'s list is the
  token endpoint's. The token is kept for the gateway's life, so later calls need no other.
- `MockResponse::getRequestUrl()` and `getRequestOptions()` give what was sent: the multipart body
  of a flow (its `flowInfo` JSON and the file), the headers.
- An HTTP 5xx, a 429 or a transport error is an `UnreachableException` without a test having to
  build one.
- The e-mail gateway's message is a `Symfony\Component\Mime\Email`: its subject, recipients,
  attachments and `Message-ID` header can be checked. Symfony Mailer's `null://null` transport
  sends nothing, for a script.

## Recorded answers

The gateways' own tests keep answers written from the platforms' published schemas, in each
package's `Tests/Fixtures`:

| Package | Fixtures |
|---|---|
| `omnibill/afnor` | `token.json`, `flow-post-202.json`, `flow-metadata-ok.json`, `flow-metadata-error.json`, `flow-search.json`, `supplier-invoice.xml`, `cdar-212.xml`, `directory-line-search.json`, `error-422.json` |
| `omnibill/chorus-pro` | `token.json`, `deposer-flux.json`, `deposer-flux-trop-gros.json`, `rechercher-fournisseur.json`, `rechercher-fournisseur-vide.json` |

The core's `docker/harness/config/recorded.php` returns a `MockHttpClient` callback that serves each
call from them by URL - the examples of the [usage pages](usage-submitting.md) run on it:

```php
$http = new MockHttpClient(require 'vendor/omnisense/omnibill/docker/harness/config/recorded.php');
```

(the path in a project that installed the core; the file is part of the package's sources.)

## Webhooks

To test a webhook controller, sign the body as a platform does - the HMAC-SHA-256, under the
decoded key, of the SHA-256 of `<body>@<timestamp>`, in base64:

```php
$key = base64_encode('the-webhook-signing-key');      // the gateway's webhook_key
$ts = (string) time();
$signature = base64_encode(hash_hmac('sha256', hash('sha256', $body.'@'.$ts, true), base64_decode($key), true));

$client->request('POST', '/invoices/webhook', server: ['HTTP_AFNOR_SIGNATURE' => $signature, 'HTTP_AFNOR_SIGNATURE_TIMESTAMP' => $ts], content: $body);
```

## In Symfony

The bundle builds the platforms' factories on `http_client`: replace it with a `MockHttpClient` in
the test environment (`framework.http_client.mock_response_factory`), and the e-mail's on the
application's mailer, which `framework.mailer.dsn: 'null://null'` silences. A gateway's service
(`omnibill.gateway.<name>`) can be replaced with `static::getContainer()->set(...)` before it is
first used.

## The package's own tests

`docker compose run --rm omnibill test` runs every package's tests in the [harness](harness.md): the
core's (the gateway and its requests, the factory and its config, the registry, the models, the
HTTP helpers, the bundle, the plain-PHP run with no framework class loaded) and each gateway's on
its fixtures.
