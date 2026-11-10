# Usage policies

Since the migration onto [omnisense/contracts](https://github.com/glitchr-omnisense/contracts)
(the kernel every omni family builds on), each gateway carries its provider's **usage policy**:
its rate limits, what its terms allow (commercial use, storage, caching, redistribution,
attribution) and what a request needs (a key), declared as data and enforced by a guard.

## Where a policy comes from

Each provider package ships a `policy.json` in the schema of omnisense/contracts
(`resources/usage-policy.schema.json`) and names it in its `composer.json`
(`extra.omnisense.policy`): `vendor/bin/omnisense-policies` prints every one an application has
installed. Its factory gives it to the gateway's `Capabilities` (`policy:`), and
`Capabilities::policy()` reads it. A gateway written before that (an application's own) gets a
policy made of its `Terms` (`Terms::policy()`: commercial use, a partnership as a contract, the
retention as caching's longest time to live, the attribution, the rest in the notes), or nothing
known. `Capabilities::$terms` stays, read from the policy when only the policy is given.

| Gateway | Rate limits | Rights | Sources (read 2026-11-10) |
|---|---|---|---|
| `email` | unknown: the mail server's | no provider terms | - |
| `afnor` | unknown: each platform's | each platform's contract | impots.gouv.fr, approved platforms and the AFNOR standard |
| `chorus-pro` | unknown: set per API by the AIFE, documented behind a PISTE account | commercial allowed; the key stays in the organisation | piste.gouv.fr/cgu |
| `odoo` | unknown: none published | the external API needs Odoo Online's Custom plan (`contract`) | Odoo 19 external API reference |
| `pennylane` | 25 requests every 5 seconds per token | internal use; storage only as the integration needs (`contract`); redistribution forbidden | Pennylane's API contract terms and rate-limiting guide |

What the terms do not say is `unknown`, never guessed; each file's `provenance.notes` says the
rest in words.

## The guard

A gateway built by its factory runs behind its `PolicyGuard`. Each request that reaches the
provider is an operation named after its typed method - `submit`, `fetchStatus`, `receive`,
`setStatus`, `lookup`, `report`, `postEntry`, `fetchEntry`, `entries`, `saveContact`,
`fetchContact`, `contacts` - checked against the policy's limits and the provider's wait before it
is made, counted after. A webhook read (`notify()`) reaches nothing and is not counted.

- A limit reached: `Exception\UsageLimitReachedException` (an `OmnibillException`, with
  `retryAfter`), the request never made.
- A 429 with `Retry-After`, or a `RateLimit` field with nothing left: the same exception, and every
  call of the gateway held until the wait is over (`Http\Answer::send()` and `ClientCredentials`
  given the provider's `Backoff`). Pennylane's `ratelimit-reset`, a Unix time, is read too.
- Without a Backoff (an application's own client calling `Answer::send()` as before 1.x), a 429 is
  still an `UnreachableException`.

## A site's configuration

```php
$registry = new Registry($factories, [
    'books' => [
        'factory' => 'pennylane',
        'options' => ['token' => '...'],
        'policy' => ['rateLimits' => [['max' => 1000, 'window' => 'day', 'operations' => ['postEntry']]]],
        'usage' => ['commercial' => true],
    ],
], $limiter);
```

- `policy` tightens the declared policy, never loosens it: a limit counting the same calls with a
  higher maximum, or a permission less strict, is refused (`InvalidPolicyException`).
- `usage` says what the site is; a use the policy forbids fails when the gateway is built
  (`UsageNotPermitted`).
- The third argument is where calls are counted: an in-memory limiter by default, a shared one
  (omnibridge/symfony's, over Symfony's rate limiter) for an application served by many processes.
- `Registry::guard()`, `policy()` and `policies()` say how each gateway is run; a credits or terms
  page can list `policies()`. In Symfony, each gateway entry takes `policy` and `usage` beside
  `options`.
