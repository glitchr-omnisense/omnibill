---
title: Symfony
order: 15
---

# Symfony

Omnibill runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel` - are not required by `omnisense/omnibill`: the application has them, and
nothing of them is loaded outside Symfony. The bundle is part of the core
(`Omnibill\Bridge\Symfony\OmnibillBundle`); omnibridge has no Omnibill bridge, and none is needed.

## Registering the bundle

Register `Omnibill\Bridge\Symfony\OmnibillBundle` (no Flex recipe):

```php
// config/bundles.php
return [
    // ...
    Omnibill\Bridge\Symfony\OmnibillBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/omnibill.yaml
omnibill:
    gateways:                      # by name: a factory and its options
        invoices:
            factory: email
            options: { from: 'Atelier Tilleul <factures@atelier.example>', bcc: '%env(default::INVOICE_ARCHIVE)%' }
        platform:
            factory: afnor
            options:
                flow_url: '%env(PA_FLOW_URL)%'
                directory_url: '%env(PA_DIRECTORY_URL)%'
                token_url: '%env(PA_TOKEN_URL)%'
                client_id: '%env(PA_CLIENT_ID)%'
                client_secret: '%env(PA_CLIENT_SECRET)%'
                webhook_key: '%env(default::PA_WEBHOOK_KEY)%'
        public:
            factory: chorus-pro
            options: { client_id: '%env(PISTE_CLIENT_ID)%', client_secret: '%env(PISTE_CLIENT_SECRET)%', login: '%env(CPRO_LOGIN)%', password: '%env(CPRO_PASSWORD)%' }
```

The whole tree, from the bundle's `configure()`:

| Key | Type | Default | |
|---|---|---|---|
| `omnibill.gateways` | map, keyed by the gateway's name | `{}` | the application's gateways |
| `omnibill.gateways.<name>.factory` | string, required, not empty | - | a factory's name: `email`, `afnor`, `chorus-pro`, or an application's own factory's `getName()` |
| `omnibill.gateways.<name>.options` | any value (a map) | `[]` | handed as they are to the factory's `create()` ([Configuration](configuration.md)) |

Processed with nothing set, the tree is `{"gateways": []}`; an empty factory is refused when the
container compiles (`The path "omnibill.gateways.x.factory" cannot contain an empty value, but got
"".`). The options are not validated then, but when the gateway is built: a required option missing
or empty is an `InvalidConfigException` naming it, the first time the gateway is asked for.

## Services

Every `omnibill/*` package installed registers its factory, **autowired**: `omnibill/email` gets
the application's `MailerInterface` and, when it has one, a `StatusStoreInterface` of its own (a
table: the statuses outlive the process); the platforms get the application's `http_client`.

```php
#[AsAlias(StatusStoreInterface::class)]
final class InvoiceStatusRepository extends ServiceEntityRepository implements StatusStoreInterface { /* ... */ }
```

The bundle knows `Omnibill\Email\EmailGatewayFactory`, `Omnibill\Afnor\AfnorGatewayFactory` and
`Omnibill\ChorusPro\ChorusProGatewayFactory`, and registers those whose class exists. A factory's
`$http` argument is the `http_client` service (null when the application has none: the factory makes
its own client); its other arguments are autowired - the e-mail's `MailerInterface` and
`StatusStoreInterface`, each null when the application has no such service (no mailer: building the
e-mail gateway is an `InvalidConfigException`; no store: this process's memory, lost at the end of
the request).

| Service id | Class | Autowiring | |
|---|---|---|---|
| `Omnibill\Registry` | `Registry` | `Registry $registry` | every configured gateway by name: `get()`, `has()`, `names()`, `options()`, `all()`, `create()` with other options. Public. |
| `omnibill.gateway.<name>` | `GatewayInterface` | `GatewayInterface $<name>` | one gateway, built by `Registry::get('<name>')` the first time it is injected |
| the factories | their class | by their class | tagged `omnibill.gateway_factory` |

The argument's name selects the gateway: `GatewayInterface $platform` is `omnibill.gateway.platform`
(Symfony's named autowiring aliases). A name that is not a valid PHP variable name (`chorus-pro`) is
still reachable by its service id or by the registry.

Nothing is built when the container compiles: a gateway is built the first time it is asked for,
and an option left empty only shows then (`InvalidConfigException`). An application's own gateway
- a class implementing `GatewayFactoryInterface` - is registered too, autoconfigured (tagged
`omnibill.gateway_factory`), and its `getName()` can be named as a `factory`
([Writing a gateway](providers.md)). The bundle registers no command, event, route or Twig
function.

## A platform's webhook

```php
#[Route('/invoices/webhook', methods: ['POST'])]
public function __invoke(Request $request, GatewayInterface $platform): Response
{
    try {
        $notification = $platform->notify($request->getContent(), $request->headers->all());
    } catch (InvalidNotificationException) {
        return new Response('', 400);
    }
    $this->bus->dispatch(new ReceiveInvoices());     // receive() from the cursor kept, out of the request
    return new Response('', 200);
}
```

The route above is the application's: the bundle registers none. Answer fast and do the work out
of the request; a callback sent twice has the same `Notification::$id` - deduplicate on it
([Receiving](usage-receiving.md#webhooks)).

The day a site's company must issue through an approved platform, `invoices` changes factory, from
`email` to `afnor`: the code that submits stays.
