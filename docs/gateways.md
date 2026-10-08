---
title: Gateways
order: 3
---

# Gateways

One contract, `Omnibill\GatewayInterface`, whoever carries the invoices:

| Method | Request | Answers |
|---|---|---|
| `submit(Invoice)` | `Request\Submit` | its `Flow`: keep the reference |
| `fetchStatus(Flow)` | `Request\FetchStatus` | the flow, updated: the platform's acknowledgement, the invoice's status |
| `receive(?cursor)` | `Request\Receive` | an `Inbox`: suppliers' invoices, statuses about yours, the next cursor |
| `setStatus(StatusChange, ?Flow)` | `Request\SetStatus` | the flow that carried it |
| `lookup(siren or siret)` | `Request\Lookup` | `list<Addressing>`: where the company receives |
| `report(EReport)` | `Request\Report` | its `Flow` |
| `notify($body, $headers)` | `Request\Notify` | a `Notification`, once the callback's signature holds |

`capabilities()` says what it carries; `supports(Request::class)` what it answers. Each method is
a shortcut for `execute(new Request\...)`. What a gateway does not do throws
`NotSupportedException` - nothing is guessed, nothing silently skipped.

| | `omnibill/email` | `omnibill/afnor` | `omnibill/chorus-pro` |
|---|---|---|---|
| Carries | Factur-X, UBL, CII, attached to an e-mail | Factur-X, UBL, CII, CDAR, FRR: flows | Factur-X, UBL, CII: deposited flows |
| `submit` | the e-mail sent: `SENT` | `POST /v1/flows`: `PENDING` | `deposer/flux`: `PENDING` |
| `fetchStatus` | the statuses recorded | the acknowledgement: Ok `DEPOSITED`, Error `REJECTED` | the invoice's `statut`, read as the reform's |
| `receive` | - | flows coming in, their files | - |
| `setStatus` | recorded by hand | the application's CDAR, as a flow | - |
| `lookup` | - | the Directory Service | - |
| `report` | - | an FRR flow | - |
| `notify` | - | webhooks, `Afnor-Signature` checked | - |
| Approved platform | no | yes | the public sector's portal |
| Needs | a mailer, a sender | the platform's addresses, OAuth 2.0 client credentials | a PISTE application, a technical account |

## Following an invoice

```php
$flow = $gateway->submit($invoice);
$row->gateway = 'platform';
$row->reference = $flow->reference;

// later, from a cron job or on the platform's webhook
$flow = $gateway->fetchStatus(new Flow($row->reference, invoiceNumber: $row->number));
if ($flow->state === FlowState::REJECTED) {
    // $flow->errors: what the platform's checks found
}

// what came in since last time
$inbox = $gateway->receive($row->cursor);
foreach ($inbox->items as $received) {
    $received->invoice;   // a supplier's invoice: its file, to read with your own tools
    $received->status;    // a status about one of yours: ->invoiceNumber, ->status, ->reason
}
$row->cursor = $inbox->cursor;
```

## Writing a gateway

A package `omnibill/<platform>`: a factory extending `Omnibill\GatewayFactory` that fills a
`Config` - its name, title, capabilities, the options it needs, its client (`omnibill.api`) and
one action per request it answers:

```php
final class PeppolGatewayFactory extends GatewayFactory
{
    // A platform reached over HTTP takes its client here: the application's, a mock in a test.
    public function __construct(private readonly ?HttpClientInterface $http = null)
    {
    }

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibill.factory_name' => 'peppol',
            'omnibill.factory_title' => 'Peppol access point',
            'omnibill.required_options' => ['api_key'],
            'omnibill.capabilities' => new Capabilities([Syntax::UBL]),
            'omnibill.api' => fn (Config $c) => new Api((string) $c['api_key'], $this->http ?? HttpClient::create()),
            'omnibill.action.submit' => new SubmitAction(),
            'omnibill.action.fetch_status' => new FetchStatusAction(),
        ]);
    }
}
```

An action implements `Action\ActionInterface` (`supports(Request)`, `execute(Request)` which sets
the request's result) and, to be handed the client, `Action\ApiAwareInterface`. Call the platform
through `Omnibill\Http\Answer::send()` (no answer, a server error or a quota: an
`UnreachableException`) and `Omnibill\Http\ClientCredentials` for an OAuth 2.0 token. Leave out
the actions the platform's documentation does not show.
