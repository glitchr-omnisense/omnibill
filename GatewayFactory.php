<?php

namespace Omnibill;

use Omnibill\Action\ActionInterface;
use Omnibill\Action\ApiAwareInterface;
use Omnibill\Exception\InvalidConfigException;
use Omnibill\Model\Capabilities;

/**
 * The Omnibus way: a gateway's factory fills a Config - its name, title,
 * capabilities, the options it needs, its client ("omnibill.api", a closure
 * of the Config: a provider's API, a token signer, a store) and its actions
 * ("omnibill.action.<name>") - and the gateway is those actions, the client
 * handed to the ones that ask for it.
 *
 * Nothing here calls anything: a factory whose provider is reached over
 * HTTP takes its client in its own constructor.
 */
abstract class GatewayFactory implements GatewayFactoryInterface
{
    public function getName(): string
    {
        return self::text($this->createConfig(), 'omnibill.factory_name');
    }

    public function create(array $options = []): GatewayInterface
    {
        $config = $this->createConfig($options);
        $required = $config->get('omnibill.required_options', []);
        $config->validateNotEmpty(\is_array($required) ? array_values(array_filter($required, 'is_string')) : []);

        $api = $config->get('omnibill.api');
        if ($api instanceof \Closure) {
            $api = $api($config);
        }
        $capabilities = $config->get('omnibill.capabilities');
        if ($capabilities instanceof \Closure) {
            $capabilities = $capabilities($config);
        }
        if (!$capabilities instanceof Capabilities) {
            throw new InvalidConfigException(\sprintf('The "%s" factory does not say what it carries (omnibill.capabilities).', self::text($config, 'omnibill.factory_name')));
        }

        $actions = [];
        foreach ($config as $key => $action) {
            if (!str_starts_with((string) $key, 'omnibill.action.')) {
                continue;
            }
            if ($action instanceof \Closure) {
                $action = $action($config);
            }
            if (!$action instanceof ActionInterface) {
                continue;
            }
            if ($action instanceof ApiAwareInterface && \is_object($api)) {
                $action->setApi($api);
            }
            $actions[] = $action;
        }

        return new Gateway(self::text($config, 'omnibill.factory_name'), self::text($config, 'omnibill.factory_title'), $capabilities, $actions);
    }

    /** @param array<string, mixed> $options */
    public function createConfig(array $options = []): Config
    {
        $config = new Config($options);
        $this->populateConfig($config);

        return $config;
    }

    abstract protected function populateConfig(Config $config): void;

    /** A key the factory fills ("omnibill.factory_name"), as a string. */
    private static function text(Config $config, string $key): string
    {
        $value = $config->get($key);

        return \is_scalar($value) ? (string) $value : '';
    }
}
