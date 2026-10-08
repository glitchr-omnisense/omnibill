<?php

namespace Omnibill\Tests\Bridge;

use Omnibill\Bridge\Symfony\OmnibillBundle;
use Omnibill\GatewayInterface;
use Omnibill\Model\Invoice;
use Omnibill\Model\Syntax;
use Omnibill\Registry;
use Omnibill\Tests\StubFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;

final class OmnibillBundleTest extends TestCase
{
    public function testTheGatewaysConfiguredAreBuiltAndInjectableByName(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->register(StubFactory::class)->setAutoconfigured(true);
        $container->register(Accounting::class)->setAutowired(true)->setPublic(true);
        $container->registerExtension((new OmnibillBundle())->getContainerExtension());
        $container->loadFromExtension('omnibill', ['gateways' => [
            'platform' => ['factory' => 'stub', 'options' => ['host' => 'pa.example']],
            'other' => ['factory' => 'stub', 'options' => ['host' => 'other.example']],
        ]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertSame(['platform', 'other'], array_keys($registry->all()));
        $accounting = $container->get(Accounting::class);
        self::assertStringStartsWith('pa.example/', $accounting->platform->submit(new Invoice('x', 'x.xml', Syntax::CII))->reference);
        self::assertStringStartsWith('other.example/', $accounting->other->submit(new Invoice('x', 'x.xml', Syntax::CII))->reference);
    }

    public function testThePackagesInstalledAreRegisteredWithTheApplicationsClient(): void
    {
        if (!class_exists(\Omnibill\Afnor\AfnorGatewayFactory::class)) {
            self::markTestSkipped('omnibill/afnor is not installed.');
        }
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->registerExtension((new OmnibillBundle())->getContainerExtension());
        $container->loadFromExtension('omnibill', ['gateways' => ['platform' => ['factory' => 'afnor', 'options' => ['flow_url' => 'https://pa.example/flow-service', 'token_url' => 'https://pa.example/token', 'client_id' => 'id', 'client_secret' => 's']]]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertContains('afnor', $registry->factories());
        self::assertTrue($registry->get('platform')->capabilities()->approved);
    }
}

final class Accounting
{
    public function __construct(public readonly GatewayInterface $platform, public readonly GatewayInterface $other)
    {
    }
}
