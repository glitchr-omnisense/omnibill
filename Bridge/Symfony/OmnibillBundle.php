<?php

namespace Omnibill\Bridge\Symfony;

use Omnibill\Afnor\AfnorGatewayFactory;
use Omnibill\ChorusPro\ChorusProGatewayFactory;
use Omnibill\Email\EmailGatewayFactory;
use Omnibill\GatewayFactoryInterface;
use Omnibill\GatewayInterface;
use Omnibill\Registry;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnibill in a Symfony application: the gateway packages installed
 * (omnibill/email, omnibill/afnor, omnibill/chorus-pro) registered, the
 * gateways built from configuration and injectable by their name:
 *
 *     omnibill:
 *         gateways:
 *             email:
 *                 factory: email
 *                 options: { from: 'factures@example.org' }
 *             platform:
 *                 factory: afnor
 *                 options: { flow_url: '%env(PA_FLOW_URL)%', directory_url: '%env(PA_DIRECTORY_URL)%', token_url: '%env(PA_TOKEN_URL)%', client_id: '%env(PA_CLIENT_ID)%', client_secret: '%env(PA_CLIENT_SECRET)%' }
 *             public:
 *                 factory: chorus-pro
 *                 options: { client_id: '%env(PISTE_CLIENT_ID)%', client_secret: '%env(PISTE_CLIENT_SECRET)%', login: '%env(CPRO_LOGIN)%', password: '%env(CPRO_PASSWORD)%' }
 *
 *     public function __construct(GatewayInterface $email, GatewayInterface $platform) {}
 *
 * Nothing is built, nor checked, when the container compiles: a gateway is
 * built the first time it is asked for, and an option left empty only shows
 * then (InvalidConfigException).
 *
 * A factory is autowired: what its constructor asks for - omnibill/email's
 * mailer and status store - is the application's service of that type, when
 * it has one; the application's http_client is given to those that call a
 * platform. An application's own factories (a GatewayFactoryInterface) are
 * registered too, autoconfigured.
 */
final class OmnibillBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnibill';

    /** The gateway packages this bundle knows, registered when installed. */
    private const FACTORIES = [EmailGatewayFactory::class, AfnorGatewayFactory::class, ChorusProGatewayFactory::class];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('gateways')
                    ->info('The gateways, by name: a factory (email, afnor, chorus-pro...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array{gateways: array<string, array{factory: string, options: array<string, mixed>}>} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(GatewayFactoryInterface::class)->addTag('omnibill.gateway_factory');

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory) && is_subclass_of($factory, GatewayFactoryInterface::class)) {
                $definition = $services->set($factory)->autowire()->tag('omnibill.gateway_factory');
                foreach ((new \ReflectionClass($factory))->getConstructor()?->getParameters() ?? [] as $parameter) {
                    if ('http' === $parameter->getName()) {
                        $definition->arg('$http', service('http_client')->nullOnInvalid());
                    }
                }
            }
        }

        $services->set(Registry::class)
            ->args([tagged_iterator('omnibill.gateway_factory'), $config['gateways']])
            ->public();

        foreach (array_keys($config['gateways']) as $name) {
            $id = 'omnibill.gateway.'.$name;
            $services->set($id, GatewayInterface::class)->factory([service(Registry::class), 'get'])->args([$name]);
            $builder->registerAliasForArgument($id, GatewayInterface::class, $name);
        }
    }
}
