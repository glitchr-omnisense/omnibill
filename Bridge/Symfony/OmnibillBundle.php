<?php

namespace Omnibill\Bridge\Symfony;

use Omnibill\Afnor\AfnorGatewayFactory;
use Omnibill\ChorusPro\ChorusProGatewayFactory;
use Omnibill\ContactsInterface;
use Omnibill\Email\EmailGatewayFactory;
use Omnibill\GatewayFactoryInterface;
use Omnibill\GatewayInterface;
use Omnibill\JournalEntriesInterface;
use Omnibill\Odoo\OdooGatewayFactory;
use Omnibill\Pennylane\PennylaneGatewayFactory;
use Omnibill\Registry;
use Omnisense\Contracts\Http\NoRetry;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnibill in a Symfony application: the gateway packages installed
 * (omnibill/email, omnibill/afnor, omnibill/chorus-pro, omnibill/odoo,
 * omnibill/pennylane) registered, the
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
 * platform, with its retries off (omnisense/contracts' Http\NoRetry):
 * Symfony's RetryableHttpClient (framework.http_client's retry_failed) never
 * submits an invoice or posts an entry twice. An application's own factories (a GatewayFactoryInterface) are
 * registered too, autoconfigured.
 *
 * Beside "options", a gateway takes "policy" (the site's tightening of the
 * provider's usage policy, never looser) and "usage" (what the site is:
 * commercial, open_source, contract). Its calls count in this process's
 * memory: the Registry service's third argument takes a shared
 * RateLimiterInterface where many processes serve the application.
 */
final class OmnibillBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnibill';

    /** The gateway packages this bundle knows, registered when installed. */
    private const FACTORIES = [EmailGatewayFactory::class, AfnorGatewayFactory::class, ChorusProGatewayFactory::class, OdooGatewayFactory::class, PennylaneGatewayFactory::class];

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
                            ->variableNode('policy')->info('The site\'s tightening of the provider\'s usage policy, in its schema\'s shape (rateLimits, rights...): never looser.')->end()
                            ->variableNode('usage')->info('What the site is: commercial, open_source, contract. A use the policy forbids fails when the gateway is built.')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array{gateways: array<string, array{factory: string, options: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}>} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(GatewayFactoryInterface::class)->addTag('omnibill.gateway_factory');

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory)) {
                $definition = $services->set($factory)->autowire()->tag('omnibill.gateway_factory');
                foreach ((new \ReflectionClass($factory))->getConstructor()?->getParameters() ?? [] as $parameter) {
                    if ('http' === $parameter->getName()) {
                        $definition->arg('$http', inline_service(HttpClientInterface::class)->factory([self::class, 'sentOnce'])->args([service('http_client')->nullOnInvalid()]));
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
            // Since 1.1: the accounting contracts, by name too (Registry::journalEntries(), contacts()).
            $services->set('omnibill.journal_entries.'.$name, JournalEntriesInterface::class)->factory([service(Registry::class), 'journalEntries'])->args([$name]);
            $builder->registerAliasForArgument('omnibill.journal_entries.'.$name, JournalEntriesInterface::class, $name);
            $services->set('omnibill.contacts.'.$name, ContactsInterface::class)->factory([service(Registry::class), 'contacts'])->args([$name]);
            $builder->registerAliasForArgument('omnibill.contacts.'.$name, ContactsInterface::class, $name);
        }
    }

    /**
     * The application's http_client with its retries off (omnisense/contracts'
     * Http\NoRetry, from contracts 1568f2c); null without one. With an older
     * omnisense/contracts the client is kept as given.
     *
     * @internal the factories' $http argument
     */
    public static function sentOnce(?HttpClientInterface $http): ?HttpClientInterface
    {
        return null !== $http && class_exists(NoRetry::class) ? NoRetry::of($http) : $http;
    }
}
