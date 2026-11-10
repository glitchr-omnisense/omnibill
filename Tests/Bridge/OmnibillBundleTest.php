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
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Omnibill\Exception\OmnibillException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Retry\GenericRetryStrategy;
use Symfony\Component\HttpClient\RetryableHttpClient;

final class OmnibillBundleTest extends TestCase
{
    public function testTheGatewaysConfiguredAreBuiltAndInjectableByName(): void
    {
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->register(StubFactory::class)->setAutoconfigured(true);
        $container->register(Accounting::class)->setAutowired(true)->setPublic(true);
        $container->registerExtension(self::extension());
        $container->loadFromExtension('omnibill', ['gateways' => [
            'platform' => ['factory' => 'stub', 'options' => ['host' => 'pa.example']],
            'other' => ['factory' => 'stub', 'options' => ['host' => 'other.example']],
        ]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertInstanceOf(Registry::class, $registry);
        self::assertSame(['platform', 'other'], array_keys($registry->all()));
        $accounting = $container->get(Accounting::class);
        self::assertInstanceOf(Accounting::class, $accounting);
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
        $container->registerExtension(self::extension());
        $container->loadFromExtension('omnibill', ['gateways' => ['platform' => ['factory' => 'afnor', 'options' => ['flow_url' => 'https://pa.example/flow-service', 'token_url' => 'https://pa.example/token', 'client_id' => 'id', 'client_secret' => 's']]]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertInstanceOf(Registry::class, $registry);
        self::assertContains('afnor', $registry->factories());
        self::assertTrue($registry->get('platform')->capabilities()->approved);
    }

    /**
     * The application's http_client with retry_failed on: Symfony's RetryableHttpClient would
     * submit the invoice again on a 503. The bundle hands it to the factories with its retries
     * off: the flow is posted once, the 503 surfaced.
     */
    public function testAnInvoiceAnswered503IsSubmittedOnceThroughARetryingClient(): void
    {
        if (!class_exists(\Omnibill\Afnor\AfnorGatewayFactory::class)) {
            self::markTestSkipped('omnibill/afnor is not installed.');
        }
        $sent = [];
        $answers = [
            new MockResponse('{"access_token":"t","token_type":"Bearer","expires_in":3600}'),
            new MockResponse('{"errorCode":"unavailable"}', ['http_code' => 503]),
            new MockResponse('{"flowId":"f1"}', ['http_code' => 202]),
        ];
        $mock = new MockHttpClient(function (string $method, string $url) use (&$sent, &$answers): MockResponse {
            $sent[] = $method.' '.$url;

            return array_shift($answers) ?? new MockResponse('{}', ['http_code' => 500]);
        });
        $container = new ContainerBuilder();
        $container->register('http_client', RetryableHttpClient::class)->setSynthetic(true);
        $container->registerExtension(self::extension());
        $container->loadFromExtension('omnibill', ['gateways' => ['platform' => ['factory' => 'afnor', 'options' => ['flow_url' => 'https://pa.example/flow-service', 'token_url' => 'https://pa.example/token', 'client_id' => 'id', 'client_secret' => 's']]]]);
        $container->compile();
        $container->set('http_client', new RetryableHttpClient($mock, new GenericRetryStrategy(GenericRetryStrategy::DEFAULT_RETRY_STATUS_CODES, 0), 3));

        $registry = $container->get(Registry::class);
        self::assertInstanceOf(Registry::class, $registry);
        try {
            $registry->get('platform')->submit(new Invoice('<Invoice/>', 'f.xml', Syntax::CII));
            self::fail('a 503');
        } catch (OmnibillException) {
        }
        self::assertSame(['POST https://pa.example/token', 'POST https://pa.example/flow-service/v1/flows'], $sent, 'the 503 was not retried by the client');
        self::assertCount(1, $answers, 'the third answer was never asked for');
    }

    public function testAMockHttpClientIsKeptAsGiven(): void
    {
        $mock = new MockHttpClient();

        self::assertSame($mock, OmnibillBundle::sentOnce($mock));
        self::assertNull(OmnibillBundle::sentOnce(null));
    }

    private static function extension(): ExtensionInterface
    {
        $extension = (new OmnibillBundle())->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}

final class Accounting
{
    public function __construct(public readonly GatewayInterface $platform, public readonly GatewayInterface $other)
    {
    }
}
