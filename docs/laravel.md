---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel, the bridge that hosts omnibase in Laravel, has no Omnibill provider, and there
is no other Laravel package for it. The registry is plain PHP: a service provider of the application
builds it once, from a config file. `omnibill/email` takes a Symfony Mailer (`symfony/mailer`, which
it requires; Laravel's own mailer is built on it, but the factory takes
`Symfony\Component\Mailer\MailerInterface`), the platforms a `symfony/http-client` client.

```php
// config/omnibill.php
return [
    'mailer_dsn' => env('OMNIBILL_MAILER_DSN', 'smtp://localhost:25'),
    'gateways' => [
        'invoices' => ['factory' => 'email', 'options' => ['from' => 'Atelier Tilleul <factures@atelier.example>']],
        'platform' => ['factory' => 'afnor', 'options' => [
            'flow_url' => env('PA_FLOW_URL'),
            'directory_url' => env('PA_DIRECTORY_URL'),
            'token_url' => env('PA_TOKEN_URL'),
            'client_id' => env('PA_CLIENT_ID'),
            'client_secret' => env('PA_CLIENT_SECRET'),
            'webhook_key' => env('PA_WEBHOOK_KEY'),
        ]],
    ],
];
```

```php
// app/Providers/OmnibillServiceProvider.php
namespace App\Providers;

use App\Billing\InvoiceStatusStore;            // the application's table, implementing Omnibill\Email\StatusStoreInterface
use Illuminate\Support\ServiceProvider;
use Omnibill\Afnor\AfnorGatewayFactory;
use Omnibill\Email\EmailGatewayFactory;
use Omnibill\Registry;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

final class OmnibillServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class, function ($app): Registry {
            $mailer = new Mailer(Transport::fromDsn(config('omnibill.mailer_dsn')));

            return new Registry([
                new EmailGatewayFactory($mailer, $app->make(InvoiceStatusStore::class)),
                new AfnorGatewayFactory(HttpClient::create()),
            ], config('omnibill.gateways'));
        });
    }
}
```

Register the provider (`bootstrap/providers.php` on Laravel 11 and later), then inject the registry:

```php
use Omnibill\Registry;

final class SendInvoice   // a queued job
{
    public function handle(Registry $registry): void
    {
        $flow = $registry->get('invoices')->submit($this->invoice);
        // store $flow->reference with the invoice
    }
}
```

A webhook route reads the raw body and the headers: `$gateway->notify($request->getContent(),
$request->headers->all())`, and answers 400 on an `InvalidNotificationException`.

Differences from the Symfony bundle: list in the provider every factory the application uses, and
ask the registry for a gateway by name (no injection by argument name). Unlike Symfony's
`%env(default::X)%`, an `env()` left unset is `null`, and a required option that is null is refused
when the gateway is built. Configuration caching keeps working: the options are plain arrays. This
page was written from the registry's API; it was not run in a Laravel application.
