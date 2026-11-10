<?php

namespace Omnibill\Tests;

use Omnibill\Action\ActionInterface;
use Omnibill\Config;
use Omnibill\Exception\OmnibillException;
use Omnibill\Exception\UnreachableException;
use Omnibill\Exception\UsageLimitReachedException;
use Omnibill\Gateway;
use Omnibill\GatewayFactory;
use Omnibill\Http\Answer;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Event;
use Omnibill\Model\Flow;
use Omnibill\Model\Invoice;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\Notification;
use Omnibill\Model\StatusChange;
use Omnibill\Model\Syntax;
use Omnibill\Model\Terms;
use Omnibill\Registry;
use Omnibill\Request\Notify;
use Omnibill\Request\Request;
use Omnibill\Request\Submit;
use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\Permission;
use Omnisense\Contracts\Policy\RateLimit;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Every call a gateway makes to its provider is guarded by the provider's usage policy, as the site tightened it. */
final class PolicyTest extends TestCase
{
    public function testCallsCountUnderTheSitesLimitAndTheCallOverItIsNeverMade(): void
    {
        $registry = new Registry([new StubFactory()], ['platform' => ['factory' => 'stub', 'options' => ['host' => 'pa.example'], 'policy' => ['rateLimits' => [['max' => 2, 'window' => 'minute', 'operations' => ['submit', 'fetchStatus']]]]]]);
        $gateway = $registry->get('platform');

        self::assertInstanceOf(Gateway::class, $gateway);
        self::assertSame($registry->guard('platform'), $gateway->guard(), 'the registry and the gateway share one guard');
        $flow = $gateway->submit(new Invoice('F-1', 'F-1.xml', Syntax::CII));
        $gateway->fetchStatus($flow);
        $gateway->setStatus(new StatusChange(LifecycleStatus::PAID, 'F-1'), $flow);
        try {
            $gateway->submit(new Invoice('F-2', 'F-2.xml', Syntax::CII));
            self::fail('A third counted call went through.');
        } catch (OmnibillException $e) {
            self::assertInstanceOf(UsageLimitReachedException::class, $e);
            self::assertStringContainsString('allows 2 a minute for fetchStatus, submit: reached', $e->getMessage());
            self::assertSame(60, $e->retryAfter);
        }
        self::assertSame(0, $registry->guard('platform')->remaining('submit'));
        self::assertNull($registry->guard('platform')->remaining('setStatus'), 'setStatus is not counted');
    }

    public function testA429AndItsRetryAfterHoldEveryCallUntilTheWaitIsOver(): void
    {
        $requests = 0;
        $http = new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('{"error":"too many requests"}', ['http_code' => 429, 'response_headers' => ['retry-after' => '120']]);
        });
        $registry = new Registry([self::overHttp($http)], ['platform' => ['factory' => 'http']]);

        try {
            $registry->get('platform')->submit(new Invoice('F-1', 'F-1.xml', Syntax::CII));
            self::fail('A 429 was read as a flow.');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(120, $e->retryAfter);
            self::assertSame('http answered 429 Too Many Requests: try again in 120 s.', $e->getMessage());
        }
        try {
            $registry->get('platform')->fetchStatus(new Flow('r-1'));
            self::fail('A call went out while the provider asked to wait.');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThanOrEqual(119, $e->retryAfter);
        }
        self::assertSame(1, $requests, 'the wait held the next request back');
    }

    public function testWithoutABackoffA429StaysUnreachable(): void
    {
        $this->expectException(UnreachableException::class);
        Answer::send(new MockHttpClient(new MockResponse('', ['http_code' => 429])), 'p', 'GET', 'https://pa.example/flows');
    }

    public function testAWebhookIsNotCounted(): void
    {
        $registry = new Registry([self::overHttp(new MockHttpClient())], ['platform' => ['factory' => 'http', 'policy' => ['rateLimits' => [['max' => 1, 'window' => 'day']]]]]);
        $gateway = $registry->get('platform');

        self::assertNull(Gateway::operation(new Notify('{}')));
        $gateway->notify('{}');
        $gateway->notify('{}');
        self::assertSame(1, $registry->guard('platform')->remaining('submit'));
    }

    public function testASiteMayTightenAPolicyNeverLoosenIt(): void
    {
        $declared = UsagePolicy::builder('http')->limit(RateLimit::perDay(1000))->commercial(Permission::Forbidden)->build();
        $tighter = new Registry([self::overHttp(new MockHttpClient(), $declared)], ['platform' => ['factory' => 'http', 'policy' => ['rateLimits' => [['max' => 100, 'window' => 'day']], 'rights' => ['storage' => 'forbidden']]]]);

        self::assertSame(100, $tighter->guard('platform')->remaining('submit'));
        self::assertSame(Permission::Forbidden, $tighter->policy('platform')->rights->storage);
        self::assertSame(['platform' => $tighter->policy('platform')], $tighter->policies());

        $looser = new Registry([self::overHttp(new MockHttpClient(), $declared)], ['platform' => ['factory' => 'http', 'policy' => ['rateLimits' => [['max' => 5000, 'window' => 'day']]]]]);
        try {
            $looser->get('platform');
            self::fail('A higher limit than the provider\'s was accepted.');
        } catch (InvalidPolicyException $e) {
            self::assertStringContainsString('5000 a day', $e->getMessage());
        }

        $commercial = new Registry([self::overHttp(new MockHttpClient(), $declared)], ['platform' => ['factory' => 'http', 'options' => ['usage' => ['commercial' => true]]]]);
        $this->expectException(UsageNotPermitted::class);
        $commercial->get('platform');
    }

    public function testTermsWrittenBeforeThePolicyGiveOne(): void
    {
        $terms = new Terms(retention: 'P3M', partner: true, price: 'EUR 1 a call', attribution: 'Data from Example', mustNotKeep: ['the token'], rules: ['Internal use only'], source: 'https://example.org/terms');
        $capabilities = new Capabilities(contracts: [Capabilities::JOURNAL_ENTRIES], terms: $terms);
        $policy = $capabilities->policy();

        self::assertSame(UsagePolicy::UNNAMED, $policy->provider);
        self::assertNull($policy->rateLimits, 'no limit known');
        self::assertSame(Permission::Contract, $policy->rights->commercial);
        self::assertSame(Permission::Contract, $policy->rights->storage);
        self::assertSame(89 * 86400, $policy->rights->caching->maxTtl, 'three months at their shortest');
        self::assertSame('Data from Example', $policy->rights->attribution->text);
        self::assertSame('https://example.org/terms', $policy->provenance->terms);
        self::assertSame('Price: EUR 1 a call. Retention: P3M. Must not keep: the token. Internal use only.', $policy->provenance->notes);
        self::assertSame($policy->toArray(), $capabilities->toArray()['policy']);
        self::assertSame($terms, $capabilities->terms);
        self::assertSame(Permission::Forbidden, (new Terms(commercial: false))->policy()->rights->commercial);
        self::assertSame(Permission::Unknown, (new Terms(commercial: null))->policy()->rights->commercial);
        self::assertSame(UsagePolicy::described(UsagePolicy::UNNAMED)->toArray(), (new Capabilities())->policy()->toArray(), 'nothing said, nothing known');
        $registry = new Registry([new StubFactory()], ['platform' => ['factory' => 'stub', 'options' => ['host' => 'pa.example']]]);
        self::assertSame('stub', $registry->policy('platform')->provider, 'the registry names it after its gateway');
    }

    public function testTheTermsAreReadFromAPolicyGivenAlone(): void
    {
        $policy = UsagePolicy::builder('x')->commercial(Permission::Allowed)->attribution('X data')->provenance(terms: 'https://x.example/terms')->build();
        $capabilities = new Capabilities(policy: $policy);

        self::assertSame($policy, $capabilities->policy());
        self::assertNotNull($capabilities->terms);
        self::assertSame([true, false, 'X data', 'https://x.example/terms'], [$capabilities->terms->commercial, $capabilities->terms->partner, $capabilities->terms->attribution, $capabilities->terms->source]);
        self::assertSame(['contracts' => [Capabilities::E_INVOICES]], array_intersect_key($capabilities->toArray(), ['contracts' => 1]));
        self::assertSame([Capabilities::E_INVOICES], $capabilities->contracts());
    }

    /** A gateway over HTTP, its Answer given the provider's Backoff: submit and fetch are each one GET, a webhook reaches nothing. */
    private static function overHttp(HttpClientInterface $http, ?UsagePolicy $policy = null): GatewayFactory
    {
        return new class($http, $policy) extends GatewayFactory {
            public function __construct(private readonly HttpClientInterface $http, private readonly ?UsagePolicy $policy)
            {
            }

            protected function populateConfig(Config $config): void
            {
                $config->defaults([
                    'omnibill.factory_name' => 'http',
                    'omnibill.factory_title' => 'Over HTTP',
                    'omnibill.capabilities' => new Capabilities(policy: $this->policy),
                    'omnibill.action.http' => fn (Config $c) => new HttpAction($this->http, $c->backoff()),
                ]);
            }
        };
    }
}

final class HttpAction implements ActionInterface
{
    public function __construct(private readonly HttpClientInterface $http, private readonly ?Backoff $backoff)
    {
    }

    public function supports(Request $request): bool
    {
        return true;
    }

    public function execute(Request $request): void
    {
        if ($request instanceof Notify) {
            $request->setResult(new Notification(Event::cases()[0]));

            return;
        }
        Answer::send($this->http, 'Example', 'GET', 'https://pa.example/flows', [], $this->backoff);
        $request->setResult(new Flow('r-1'));
    }
}
