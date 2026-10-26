---
title: Writing a gateway
order: 80
---

# Writing a gateway

A gateway is a factory that extends `Omnibill\GatewayFactory` and one action per request it
answers. It can live in the application (an access point the application uses) or be a package of
its own, as `omnibill/afnor` and `omnibill/chorus-pro` are.

## The rules

- **Carry, do not produce.** A gateway sends the file it is given and reads none beyond what it
  must to route it (a lifecycle message's status). It keeps no books.
- **One action per request, only those the platform documents.** A request no action supports is a
  `NotSupportedException`, and `supports()` says so beforehand; a factory may leave an action out
  when an option is missing (a closure of the `Config` that returns null).
- **Every action answers.** `execute()` sets the request's result (`setResult()`), or throws an
  `OmnibillException`; returning without a result is a `ProviderException` of the gateway.
- **Through the helpers.** Call the platform with `Omnibill\Http\Answer::send()`: no answer, a 5xx
  or a 429 is then an `UnreachableException` - nothing transmitted, try again - and the rest comes
  back to read. Ask an OAuth 2.0 token with `Omnibill\Http\ClientCredentials`, `reset()` it on a
  401 and try once more.
- **Errors.** Refused credentials are an `InvalidKeyException`; any other refusal a
  `ProviderException` with the platform's code; a callback that does not hold an
  `InvalidNotificationException`; what the invoice lacks for this gateway (a number, an e-mail) an
  `InvalidConfigException` before any call.
- **A flow to keep.** `submit()`, `report()` and `setStatus()` answer a `Flow` whose `reference` is
  what the platform names it by; `fetchStatus()` takes a flow rebuilt from that reference and the
  invoice's number.

## A gateway of the application's

A sketch of a Peppol access point with an API key and two routes:

```php
<?php // peppol.php

require __DIR__.'/vendor/autoload.php';

use Omnibill\Action\ActionInterface;
use Omnibill\Action\ApiAwareInterface;
use Omnibill\Action\ApiAwareTrait;
use Omnibill\Config;
use Omnibill\Exception\ProviderException;
use Omnibill\GatewayFactory;
use Omnibill\Http\Answer;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Flow;
use Omnibill\Model\FlowState;
use Omnibill\Model\Invoice;
use Omnibill\Model\Syntax;
use Omnibill\Request\FetchStatus;
use Omnibill\Request\Receive;
use Omnibill\Request\Request;
use Omnibill\Request\Submit;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** An access point's API: a key, two routes. */
final class PeppolApi
{
    public function __construct(private readonly HttpClientInterface $http, private readonly string $url, #[\SensitiveParameter] private readonly string $key)
    {
    }

    public function call(string $method, string $path, array $options = []): array
    {
        $options['headers'] = ['X-Api-Key' => $this->key] + ($options['headers'] ?? []);
        $answer = Answer::send($this->http, 'peppol', $method, $this->url.$path, $options);
        if ($answer->status >= 400) {
            throw new ProviderException('peppol', sprintf('HTTP %d', $answer->status), (string) $answer->status);
        }

        return $answer->json();
    }
}

final class PeppolSubmitAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<PeppolApi> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = PeppolApi::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Submit;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof Submit);
        $sent = $this->api->call('POST', '/documents', ['body' => $request->invoice->content, 'headers' => ['Content-Type' => 'application/xml']]);
        $request->setResult(new Flow((string) $sent['id'], state: FlowState::PENDING, trackingId: $request->trackingId, invoiceNumber: $request->invoice->number));
    }
}

final class PeppolFetchStatusAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<PeppolApi> */
    use ApiAwareTrait;

    public function __construct()
    {
        $this->apiClass = PeppolApi::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof FetchStatus;
    }

    public function execute(Request $request): void
    {
        \assert($request instanceof FetchStatus);
        $state = $this->api->call('GET', '/documents/'.rawurlencode($request->flow->reference))['state'] ?? null;
        $request->setResult($request->flow->with(state: match ($state) { 'delivered' => FlowState::ACCEPTED, 'failed' => FlowState::REJECTED, default => FlowState::PENDING }));
    }
}

final class PeppolGatewayFactory extends GatewayFactory
{
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibill.factory_name' => 'peppol',
            'omnibill.factory_title' => 'Peppol access point',
            'omnibill.required_options' => ['url', 'api_key'],
            'omnibill.capabilities' => new Capabilities([Syntax::UBL]),
            'omnibill.api' => fn (Config $c) => new PeppolApi($this->http ?? HttpClient::create(), rtrim((string) $c['url'], '/'), (string) $c['api_key']),
            'omnibill.action.submit' => new PeppolSubmitAction(),
            'omnibill.action.fetch_status' => new PeppolFetchStatusAction(),
        ]);
    }
}

$http = new MockHttpClient([new MockResponse('{"id":"doc-1"}', ['http_code' => 201]), new MockResponse('{"state":"delivered"}')]);
$gateway = (new PeppolGatewayFactory($http))->create(['url' => 'https://ap.example/api', 'api_key' => 'test']);

$flow = $gateway->submit(new Invoice('<Invoice/>', 'F-1.xml', Syntax::UBL, 'F-1'));
echo $gateway->getTitle(), ': ', $flow->reference, ' ', $flow->state->value, "\n";
echo $gateway->fetchStatus($flow)->state->value, "\n";
var_dump($gateway->supports(Receive::class));
try {
    (new PeppolGatewayFactory())->create(['url' => 'https://ap.example/api']);
} catch (\Omnibill\Exception\InvalidConfigException $e) {
    echo $e->getMessage(), "\n";
}
```

```
$ php peppol.php
Peppol access point: doc-1 pending
accepted
bool(false)
The "peppol" gateway needs: api_key.
```

(run on 2026-10-26 on a `MockHttpClient`, PHP 8.4; no access point was called.)

In Symfony, the factory is autoconfigured (tagged `omnibill.gateway_factory`) as soon as it is a
service, and named in `omnibill.gateways.<name>.factory`.

## The factory's Config

`populateConfig(Config $config)` sets, with `$config->defaults([...])`:

| Key | |
|---|---|
| `omnibill.factory_name` | the factory's name, what `factory:` names |
| `omnibill.factory_title` | a readable name, `getTitle()` |
| `omnibill.required_options` | the options `create()` refuses to go without |
| `omnibill.capabilities` | a `Capabilities` (or a closure of the `Config` giving one): required |
| `omnibill.api` | the client the actions work with, or a closure of the `Config` building it at `create()` |
| `omnibill.action.<name>` | an action, or a closure of the `Config` giving one or null |
| every other option | its default |

`Config::bool()` and `Config::list()` read a boolean and a list as an environment variable gives
them (`"1"`, `"a,b"`).

## A package of its own

The shipped gateways' layout, to copy: name `omnibill/<platform>`, namespace `Omnibill\<Platform>\`,
`require` `php` `>=8.2`, `omnisense/omnibill` `^1.0@dev` and what the platform needs
(`symfony/http-client`, `ext-dom`); an `Api` class and an `Action/` directory with an
`AbstractAction` holding the `ApiAwareTrait`; tests on answers written from the platform's
published schemas, in `Tests/Fixtures`; a README with the sources read, what they leave out, and a
"Responsibility" section. For the bundle to register the factory without the application declaring
it, its class must be in `OmnibillBundle::FACTORIES`; for the harness, in `docker/harness/plugins.php`,
`config/gateways.php` and `config/recorded.php`.
