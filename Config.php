<?php

namespace Omnibill;

use Omnibill\Exception\InvalidConfigException;

/**
 * A gateway's configuration while its factory builds it: the options given,
 * the factory's defaults under them, and the keys that must be filled -
 * omnisense/contracts' Config, with omnibill's prefix ("omnibill.factory_name",
 * "omnibill.backoff"...) and its InvalidConfigException. Every gateway takes
 * two options besides its own: "policy" (the site's tightening of its usage
 * policy) and "usage" (what the site says of itself: commercial,
 * open_source, contract).
 */
final class Config extends \Omnisense\Contracts\Provider\Config
{
    /** @var string */
    public const PREFIX = 'omnibill';

    /** @param string[] $keys */
    public function validateNotEmpty(array $keys): void
    {
        $missing = array_values(array_filter($keys, fn (string $key) => !isset($this[$key]) || '' === $this[$key] || [] === $this[$key]));
        if ($missing) {
            throw new InvalidConfigException(\sprintf('The "%s" gateway needs: %s.', $this->string('omnibill.factory_name') ?? '?', implode(', ', $missing)));
        }
    }

    /**
     * A list given as one environment variable arrives as a single
     * comma-separated string: split here, at run time.
     *
     * @return list<string>
     */
    public function list(string $key): array
    {
        $list = [];
        foreach ((array) $this->get($key, []) as $value) {
            foreach (\is_scalar($value) ? explode(',', (string) $value) : [] as $one) {
                if ('' !== trim($one)) {
                    $list[] = trim($one);
                }
            }
        }

        return $list;
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }
}
