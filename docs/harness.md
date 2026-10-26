---
title: The Docker harness
order: 70
---

# The Docker harness

`docker/` runs this package with every `omnibill/*` gateway installed - from GitHub (branch 1.x),
or from the checkouts beside this one when `OMNIBILL_PLUGINS=../..` is set in `docker/.env`.

```sh
cd docker && cp .env.dist .env       # a mailer, a platform, PISTE, when you have them
docker compose run --rm omnibill bare
```

| Command | |
|---|---|
| `gateways` | the gateways installed and configured, what each carries and does - nothing called |
| `bare` | plain PHP: the registry built by hand, an invoice through each gateway, what PHP loaded |
| `bare --live` | the same, the e-mail sent for real through `MAILER_DSN` |
| `bare --json` | the same, whole: every class and file loaded |
| `test` | every package's tests |

## Bare: no bundle, no container

`docker/harness/bin/bare` requires the autoloader and nothing else. It builds the `Registry` from
the gateway packages installed and sends an invoice through each - the e-mail to an in-memory
transport (or `MAILER_DSN` with `--live`), the platforms answering from their packages' recorded
answers - then lists what PHP loaded, and exits 1 if a class of a framework is among it:

```
$ docker compose run --rm omnibill bare
Omnibill in bare PHP: the registry built by hand, no bundle, no container; the platforms' recorded answers.

  email       E-mail: carries Factur-X, UBL, CII; approved platform no, public sector no; does submit fetch set
  afnor       Approved platform (XP Z12-013): carries Factur-X, UBL, CII, CDAR, FRR; approved platform yes, public sector yes; does submit fetch receive set lookup report
  chorus-pro  Chorus Pro: carries Factur-X, UBL, CII; approved platform no, public sector yes; does submit fetch

email: "Facture F-2026-042" to compta@erable.example, F-2026-042.pdf attached (application/pdf), through in memory
  statuses: sent -> paid

afnor: flow 8f1d2c3b-4a5e-4f60-9a7b-1c2d3e4f5a6b pending, then accepted (deposited)
  received: FA-77.xml; statuses: F-2026-042 paid
  directory, SIREN 702042755: 702042755 WK Enabled; 702042755_70204275500240_ACHATS WK Upcoming

chorus-pro: deposited as CPP0021100000000000000023; accepted, made_available (MISE_A_DISPOSITION)

Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Component\Mailer, Symfony\Component\Mime, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, a bundle or bridge, Doctrine's ORM or DBAL, Twig): none
```

(2026-10-08) `doctrine/lexer` may be loaded: `symfony/mime` reads addresses with it; it is a
tokenizer, not Doctrine's ORM. `Tests/BareTest.php` runs `bare --json` in a process of its own and
checks the list.

The image is `php:8.4-cli-alpine` with Composer; the harness's packages live in the `harness`
volume of the `omnibill-harness` project.

## Run on 2026-10-26

The same script, `bare` with no option, run on the packages' checkouts through
`OMNIBILL_AUTOLOAD=vendor/autoload.php php core/docker/harness/bin/bare` (PHP 8.4, no network),
printed the lines above unchanged.

## The entrypoint

`docker/entrypoint.sh` runs `docker/harness/setup.php` first: it writes the harness's
`composer.json` and `phpunit.xml` in the `harness` volume - this core from its checkout, each
gateway from its checkout under `OMNIBILL_PLUGINS` when one is there, from
`https://github.com/glitchr-omnisense/omnibill-<slug>.git` (branch 1.x) otherwise. Then
`composer install` the first time, `composer update` when `composer.json` is newer than the lock,
and the command: `test` (PHPUnit), `bare`, `composer`, `sh` or `php` as they are, anything else the
console (`gateways` by default).

`docker/harness/config/gateways.php` says which environment variables configure each gateway
(`INVOICE_FROM`; `PA_FLOW_URL`, `PA_TOKEN_URL`, `PA_CLIENT_ID`, `PA_CLIENT_SECRET`, and
`PA_DIRECTORY_URL`, `PA_WEBHOOK_KEY`; `PISTE_CLIENT_ID`, `PISTE_CLIENT_SECRET`, `CPRO_LOGIN`,
`CPRO_PASSWORD`, `CPRO_SANDBOX` - 1 by default) and the example options `bare` uses instead.
