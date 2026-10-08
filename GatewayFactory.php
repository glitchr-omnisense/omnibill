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
        return $this->createConfig()['omnibill.factory_name'];
    }

    public function create(array $options = []): GatewayInterface
    {
        $config = $this->createConfig($options);
        $config->validateNotEmpty($config->get('omnibill.required_options', []));

        $api = $config->get('omnibill.api');
        if ($api instanceof \Closure) {
            $api = $api($config);
        }
        $capabilities = $config->get('omnibill.capabilities');
        if ($capabilities instanceof \Closure) {
            $capabilities = $capabilities($config);
        }
        if (!$capabilities instanceof Capabilities) {
            throw new InvalidConfigException(\sprintf('The "%s" factory does not say what it carries (omnibill.capabilities).', $config['omnibill.factory_name']));
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
            if ($action instanceof ApiAwareInterface && null !== $api) {
                $action->setApi($api);
            }
            $actions[] = $action;
        }

        return new Gateway($config['omnibill.factory_name'], $config['omnibill.factory_title'], $capabilities, $actions);
    }

    public function createConfig(array $options = []): Config
    {
        $config = new Config($options);
        $this->populateConfig($config);

        return $config;
    }

    abstract protected function populateConfig(Config $config): void;
}
