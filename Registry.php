<?php

namespace Omnibill;

use Omnibill\Exception\InvalidConfigException;
use Omnibill\Exception\NotSupportedException;
use Omnibill\Model\Capabilities;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Provider\ProviderFactoryInterface;
use Omnisense\Contracts\Provider\ProviderInterface;
use Omnisense\Contracts\Provider\Registry as BaseRegistry;

/**
 * The application's gateways by name, each built once from its factory and
 * options, each behind its provider's usage policy:
 *
 *   new Registry([new EmailGatewayFactory($mailer), new AfnorGatewayFactory($http), new PennylaneGatewayFactory($http)], [
 *       'email' => ['factory' => 'email', 'options' => ['from' => 'factures@example.org']],
 *       'platform' => ['factory' => 'afnor', 'options' => ['flow_url' => 'https://api.flow.example-pa.fr/flow-service', 'token_url' => '...', 'client_id' => '...', 'client_secret' => '...']],
 *       'books' => [
 *           'factory' => 'pennylane',
 *           'options' => ['token' => '...'],
 *           'policy' => ['rateLimits' => [['max' => 1000, 'window' => 'day', 'operations' => ['postEntry']]]],
 *       ],
 *   ], $limiter);
 *
 * omnisense/contracts' Registry: "policy" tightens a gateway's usage policy
 * (never looser), "usage" says what the site is (a use the policy forbids
 * fails at once), both beside "options" or in them; every gateway's calls
 * count in the limiter given, an in-memory one by default (a shared one for
 * an application served by many processes). guard(), policy() and
 * policies() say how each gateway is run.
 */
final class Registry extends BaseRegistry
{
    /** @var string */
    public const PREFIX = Config::PREFIX;

    private readonly RateLimiterInterface $counts;

    /**
     * @param iterable<GatewayFactoryInterface>                                                                                                  $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}> $config
     * @param ?RateLimiterInterface                                                                                                              $limiter   where every gateway's calls are counted
     */
    public function __construct(iterable $factories, array $config, ?RateLimiterInterface $limiter = null)
    {
        $this->counts = $limiter ?? new InMemoryRateLimiter();
        parent::__construct($factories, $config, $this->counts);
    }

    public function get(string $name): GatewayInterface
    {
        $gateway = parent::get($name);

        return $gateway instanceof GatewayInterface ? $gateway : throw new InvalidConfigException(\sprintf('The "%s" gateway is no omnibill gateway.', $name));
    }

    /**
     * A gateway built afresh, its configured options with $overrides over
     * them - an instance typed in a back office, say. Not kept: get() still gives
     * the configured one. It counts in the same limiter.
     *
     * @param array<string, mixed> $overrides
     */
    public function create(string $name, array $overrides = []): GatewayInterface
    {
        $gateway = $this->factory($name)->create([self::PREFIX.'.name' => $name, self::PREFIX.'.limiter' => $this->counts] + array_replace($this->options($name), $overrides));

        return $gateway instanceof GatewayInterface ? $gateway : throw new InvalidConfigException(\sprintf('The "%s" gateway is no omnibill gateway.', $name));
    }

    /** @return array<string, GatewayInterface> */
    public function all(): array
    {
        $all = [];
        foreach ($this->names() as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /** A gateway by name, as the journal-entries contract: NotSupportedException when it does not keep it. */
    public function journalEntries(string $name): JournalEntriesInterface
    {
        $gateway = $this->get($name);
        if (!$gateway instanceof JournalEntriesInterface || !$gateway->capabilities()->keeps(Capabilities::JOURNAL_ENTRIES)) {
            throw new NotSupportedException(\sprintf('The "%s" gateway posts no journal entries.', $name));
        }

        return $gateway;
    }

    /** A gateway by name, as the contacts contract: NotSupportedException when it does not keep it. */
    public function contacts(string $name): ContactsInterface
    {
        $gateway = $this->get($name);
        if (!$gateway instanceof ContactsInterface || !$gateway->capabilities()->keeps(Capabilities::CONTACTS)) {
            throw new NotSupportedException(\sprintf('The "%s" gateway keeps no contacts.', $name));
        }

        return $gateway;
    }

    protected function factory(string $name): ProviderFactoryInterface
    {
        $gateway = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" gateway; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$gateway['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" gateway; installed: %s.', $gateway['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }

    protected function notSupported(ProviderInterface $provider, string $name, string $capability): \Throwable
    {
        return NotSupportedException::capability($provider, $name, $capability);
    }
}
