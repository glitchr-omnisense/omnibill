<?php

namespace Omnibill;

use Omnibill\Exception\InvalidConfigException;
use Omnibill\Exception\NotSupportedException;
use Omnibill\Model\Capabilities;

/**
 * The application's gateways by name, each built once from its factory and
 * options:
 *
 *   new Registry([new EmailGatewayFactory($mailer), new AfnorGatewayFactory($http)], [
 *       'email' => ['factory' => 'email', 'options' => ['from' => 'factures@example.org']],
 *       'platform' => ['factory' => 'afnor', 'options' => ['flow_url' => 'https://api.flow.example-pa.fr/flow-service', 'token_url' => '...', 'client_id' => '...', 'client_secret' => '...']],
 *   ]);
 */
final class Registry
{
    /** @var array<string, GatewayFactoryInterface> */
    private array $factories = [];

    /** @var array<string, GatewayInterface> */
    private array $gateways = [];

    /**
     * @param iterable<GatewayFactoryInterface>                                      $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>}> $config
     */
    public function __construct(iterable $factories, private readonly array $config)
    {
        foreach ($factories as $factory) {
            $this->factories[$factory->getName()] = $factory;
        }
    }

    public function get(string $name): GatewayInterface
    {
        if (isset($this->gateways[$name])) {
            return $this->gateways[$name];
        }
        $gateway = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" gateway; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));
        $factory = $this->factories[$gateway['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" gateway; installed: %s.', $gateway['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));

        return $this->gateways[$name] = $factory->create($gateway['options'] ?? []);
    }

    public function has(string $name): bool
    {
        return isset($this->config[$name]);
    }

    /** @return array<string, mixed> the options a gateway is configured with */
    public function options(string $name): array
    {
        return $this->config[$name]['options'] ?? [];
    }

    /**
     * A gateway built afresh, its configured options with $overrides over
     * them - an instance typed in a back office, say. Not kept: get() still gives
     * the configured one.
     *
     * @param array<string, mixed> $overrides
     */
    public function create(string $name, array $overrides = []): GatewayInterface
    {
        $gateway = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" gateway; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));
        $factory = $this->factories[$gateway['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" gateway; installed: %s.', $gateway['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));

        return $factory->create(array_replace($gateway['options'] ?? [], $overrides));
    }

    /** @return list<string> the configured gateways' names */
    public function names(): array
    {
        return array_keys($this->config);
    }

    /** @return array<string, GatewayInterface> */
    public function all(): array
    {
        $all = [];
        foreach (array_keys($this->config) as $name) {
            $all[$name] = $this->get($name);
        }

        return $all;
    }

    /** @return string[] the factories installed: what `factory:` may name */
    public function factories(): array
    {
        return array_keys($this->factories);
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
}
