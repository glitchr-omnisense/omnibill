---
title: Symfony
order: 4
---

# Symfony

Omnibill runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel` - are not required by `glitchr/omnibill`: the application has them, and
nothing of them is loaded outside Symfony.

Register `Omnibill\Bridge\Symfony\OmnibillBundle` (no Flex recipe):

```php
// config/bundles.php
return [
    // ...
    Omnibill\Bridge\Symfony\OmnibillBundle::class => ['all' => true],
];
```

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

Every `omnibill/*` package installed registers its factory, **autowired**: `omnibill/email` gets
the application's `MailerInterface` and, when it has one, a `StatusStoreInterface` of its own (a
table: the statuses outlive the process); the platforms get the application's `http_client`.

```php
#[AsAlias(StatusStoreInterface::class)]
final class InvoiceStatusRepository extends ServiceEntityRepository implements StatusStoreInterface { /* ... */ }
```

What is autowired:

| Service | |
|---|---|
| `GatewayInterface $invoices`, `GatewayInterface $platform` | one gateway by the argument's name (the configured name) |
| `Registry` | every configured gateway by name (`get()`, `has()`, `names()`, `options()`, `create()`) |

Nothing is built when the container compiles: a gateway is built the first time it is asked for,
and an option left empty only shows then (`InvalidConfigException`). An application's own gateway
- a class implementing `GatewayFactoryInterface` - is registered too, autoconfigured.

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

The day a site's company must issue through an approved platform, `invoices` changes factory, from
`email` to `afnor`: the code that submits stays.
