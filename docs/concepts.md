---
title: Concepts
order: 20
---

# Concepts

## Gateways

A gateway is one way to transmit and receive invoices, configured: `Omnibill\GatewayInterface`.

| Method | Request | Answers |
|---|---|---|
| `getName(): string` | - | the factory's name: `email`, `afnor`, `chorus-pro` |
| `getTitle(): string` | - | a readable name: `E-mail`, `Approved platform (XP Z12-013)`, `Chorus Pro` |
| `capabilities(): Capabilities` | - | what it carries, and whether it is an approved platform's channel |
| `supports(string $request): bool` | - | whether it answers that request class (`Request\Receive::class`...) |
| `execute(Request $request): Request` | any | the same request, answered |
| `submit(Invoice $invoice, ?string $trackingId = null): Flow` | `Request\Submit` | the invoice's flow: keep its reference |
| `fetchStatus(Flow $flow): Flow` | `Request\FetchStatus` | the flow as the gateway has it now |
| `receive(?string $cursor = null, int $limit = 25): Inbox` | `Request\Receive` | what arrived since the cursor |
| `setStatus(StatusChange $change, ?Flow $flow = null): Flow` | `Request\SetStatus` | the flow that carried it, or the invoice's, updated |
| `lookup(string $identifier): array` | `Request\Lookup` | `list<Addressing>`: where a SIREN or SIRET receives |
| `report(EReport $report, ?string $trackingId = null): Flow` | `Request\Report` | the report's flow |
| `notify(string $body, array $headers = []): Notification` | `Request\Notify` | a webhook's call, its signature checked |

Each typed method is `execute(new Request\...)` and the request's result. A request no action
supports is a `NotSupportedException` (`The "chorus-pro" gateway does not support Receive.`);
`supports()` says so beforehand, by asking each action about an instance of the class built without
its constructor.

`Omnibill\Gateway` is the one implementation: `new Gateway(string $name, string $title,
Capabilities $capabilities, array $actions)`. `execute()` hands the request to the first action that
supports it; an action that returns without setting a result is a `ProviderException` (`<class>
left the request unanswered.`).

## Requests

`Omnibill\Request\Request` is abstract: `setResult(mixed)`, `getResult()`, `isAnswered()`. The
requests, with their public `readonly` properties and their typed getter:

| Request | Properties | Result |
|---|---|---|
| `Submit` | `invoice` (`Invoice`), `trackingId` (`?string`) | `getFlow(): ?Flow` |
| `FetchStatus` | `flow` (`Flow`) | `getFlow(): ?Flow` |
| `Receive` | `cursor` (`?string`), `limit` (`int`, 25) | `getInbox(): ?Inbox` |
| `SetStatus` | `change` (`StatusChange`), `flow` (`?Flow`) | `getFlow(): ?Flow` |
| `Lookup` | `identifier` (`string`) | `getAddressings(): ?array` |
| `Report` | `report` (`EReport`), `trackingId` (`?string`) | `getFlow(): ?Flow` |
| `Notify` | `body` (`string`), `headers` (`array<string, string\|string[]>`) | `getNotification(): ?Notification`; `header(string $name): ?string` whatever the case it came in |

## Actions

`Omnibill\Action\ActionInterface`: `supports(Request $request): bool` and `execute(Request
$request): void`, which sets the request's result or throws an `OmnibillException`. An action that
works through the gateway's client implements `Action\ApiAwareInterface` (`setApi(object $api)`);
`Action\ApiAwareTrait` holds it in `$this->api` and refuses a client that is not of the class it
names in `$this->apiClass` (`InvalidConfigException`).

## Factories and their Config

`Omnibill\GatewayFactoryInterface`: `getName(): string` and `create(array $options = []):
GatewayInterface`. The packages extend `Omnibill\GatewayFactory`, whose `create()`:

1. fills a `Config` with the options given and the factory's `populateConfig()` defaults under them;
2. checks every key of `omnibill.required_options` is set and not empty (`InvalidConfigException`:
   `The "afnor" gateway needs: client_secret.`);
3. builds the client: `omnibill.api`, a closure of the `Config` called now, or an object;
4. reads `omnibill.capabilities` (a `Capabilities`, or a closure giving one; missing:
   `InvalidConfigException`);
5. collects every `omnibill.action.<name>`: an `ActionInterface`, or a closure of the `Config` giving
   one - or null, which leaves the action out (that is how `afnor` drops `lookup` without a
   `directory_url`); each `ApiAwareInterface` action is handed the client;
6. returns a `Gateway` of `omnibill.factory_name`, `omnibill.factory_title`, the capabilities and
   the actions.

Building calls nothing; a factory reached over HTTP takes its client in its own constructor.

`Omnibill\Config` is an `ArrayObject` of the options, with:

| Method | |
|---|---|
| `defaults(array $defaults): self` | sets each key not set yet |
| `validateNotEmpty(array $keys): void` | `InvalidConfigException` naming the keys unset, null, `''` or `[]` |
| `get(string $key, mixed $default = null)` | |
| `bool(string $key): bool` | a boolean, or `"1"`, `"true"`, `"yes"`, `"on"` (any case) as an environment variable gives it |
| `list(string $key): list<string>` | a list, each value split on commas and trimmed: `'a@x, b@x'` is two |

## The registry

`Omnibill\Registry`, built from the factories and the gateways' configuration by name:

| Method | |
|---|---|
| `get(string $name): GatewayInterface` | built once and kept. `No "x" gateway; configured: invoices.`, `No "x" factory for the "y" gateway; installed: email, afnor.` |
| `create(string $name, array $overrides = []): GatewayInterface` | built afresh, the configured options with `$overrides` over them (an instance typed in a back office); not kept |
| `has(string $name): bool` | whether the name is configured |
| `options(string $name): array` | its configured options |
| `names(): list<string>`, `all(): array<string, GatewayInterface>` | |
| `factories(): list<string>` | the factories installed: what `factory` may name |

## HTTP helpers

Two classes of the core every platform gateway calls through:

- `Omnibill\Http\Answer::send(HttpClientInterface $http, string $provider, string $method, string
  $url, array $options = []): Answer` - one request, read whole, with a 10-second timeout unless the
  options give one. No connection, a timeout, a transport error, an HTTP 5xx or a 429 is an
  `UnreachableException` (the platform said nothing about the request: try again); any other answer
  comes back as an `Answer`: `status`, `headers` (lower-case names), `body`, `header($name)`, and
  `json()` (the body as a JSON object, or a `ProviderException`).
- `Omnibill\Http\ClientCredentials` - an OAuth 2.0 access token by the client credentials grant:
  `new ClientCredentials($http, $provider, $tokenUrl, $clientId, $clientSecret, ?$scope, ?$clock)`,
  `token()` (asked once, kept until 60 seconds before `expires_in` - 3600 when the endpoint does not
  say), `reset()` (forget it: after a 401). A 400, 401 or 403 from the token endpoint, or an answer
  without `access_token`, is an `InvalidKeyException`.

The models these requests carry: [Models](models.md).
