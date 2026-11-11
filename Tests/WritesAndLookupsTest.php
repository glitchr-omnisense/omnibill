<?php

namespace Omnibill\Tests;

use Omnibill\Action\ActionInterface;
use Omnibill\Exception\NotSupportedException;
use Omnibill\Exception\OutcomeUnknownException;
use Omnibill\Exception\UnreachableException;
use Omnibill\Gateway;
use Omnibill\Http\Answer;
use Omnibill\Model\Addressing;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Contact;
use Omnibill\Model\ContactKind;
use Omnibill\Model\EReporting\EReportData;
use Omnibill\Model\EReporting\Payment;
use Omnibill\Model\EReporting\RateTotal;
use Omnibill\Model\EReporting\Transaction;
use Omnibill\Model\EReporting\TransactionKind;
use Omnibill\Model\Flow;
use Omnibill\Model\FlowKind;
use Omnibill\Model\Inbox;
use Omnibill\Model\JournalEntry;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\Received;
use Omnibill\Model\StatusChange;
use Omnibill\Registry;
use Omnibill\Request\Accounting\FindContact;
use Omnibill\Request\Accounting\FindEntry;
use Omnibill\Request\FindFlow;
use Omnibill\Request\Receive;
use Omnibill\Request\Request;
use Omnibill\Request\SetStatus;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Since 1.2: a write whose outcome is unknown told apart from one that
 * never left, writes found again by the application's key, an inbox read
 * by kinds, a gateway per account, the e-reporting data model.
 */
final class WritesAndLookupsTest extends TestCase
{
    private static function send(MockResponse $answer, string $method = 'POST', ?bool $write = null): void
    {
        Answer::send(new MockHttpClient($answer), 'p', $method, 'https://api.example/v1/things', [], null, $write);
    }

    public function testAWriteThatMayHaveBeenTakenIsAnOutcomeUnknownAndOneThatNeverLeftIsNot(): void
    {
        $cases = [
            'a 5xx to a POST' => [new MockResponse('down', ['http_code' => 502]), 'POST', null, true],
            'a 5xx to a PUT' => [new MockResponse('', ['http_code' => 500]), 'PUT', null, true],
            'a timeout on a POST' => [new MockResponse('', ['error' => 'Idle timeout reached for "https://api.example/v1/things".']), 'POST', null, true],
            'an RPC call said to write' => [new MockResponse('', ['http_code' => 503]), 'POST', true, true],
            'a 5xx to a GET' => [new MockResponse('', ['http_code' => 503]), 'GET', null, false],
            'a search sent as a POST' => [new MockResponse('', ['http_code' => 503]), 'POST', false, false],
            'a 429 to a POST' => [new MockResponse('', ['http_code' => 429]), 'POST', null, false],
            'a host that did not resolve' => [new MockResponse('', ['error' => 'Could not resolve host: api.example']), 'POST', null, false],
            'a connection refused' => [new MockResponse('', ['error' => 'Failed to connect to api.example port 443: Connection refused']), 'POST', null, false],
        ];
        foreach ($cases as $case => [$answer, $method, $write, $unknown]) {
            try {
                self::send($answer, $method, $write);
                self::fail($case);
            } catch (UnreachableException $e) {
                self::assertSame($unknown, $e instanceof OutcomeUnknownException, $case);
            }
        }
    }

    public function testFlowsAndEntriesAreFoundAgainWhereTheGatewaySupportsIt(): void
    {
        $gateway = new Gateway('t', 'T', new Capabilities(), [new LookupAction()]);

        self::assertTrue($gateway->supports(FindFlow::class));
        self::assertSame('F-1', $gateway->findFlow('order-1')?->reference);
        self::assertNull($gateway->findFlow('order-2'), 'none: an answer too');
        self::assertSame(['findFlow', 'findEntry'], [Gateway::operation(new FindFlow('x')), Gateway::operation(new FindEntry(new JournalEntry('OD', new \DateTimeImmutable(), 'x')))]);
        self::assertFalse($gateway->supports(FindEntry::class));
        $this->expectException(NotSupportedException::class);
        $gateway->findEntry(new JournalEntry('OD', new \DateTimeImmutable('2026-11-11'), 'Frais', key: 'misc:1'));
    }

    public function testContactsAreFoundAgainByTheirKeyWhereTheGatewaySupportsIt(): void
    {
        $gateway = new Gateway('t', 'T', new Capabilities(), [new LookupAction()]);
        $edf = new Contact(ContactKind::SUPPLIER, 'EDF', 'supplier:edf');

        self::assertTrue($gateway->supports(FindContact::class));
        self::assertSame(['P-7', 'supplier:edf'], [$gateway->findContact($edf)?->reference, $gateway->findContact($edf)?->key]);
        self::assertNull($gateway->findContact(new Contact(ContactKind::SUPPLIER, 'EDF', 'supplier:other')), 'none: an answer too');
        self::assertSame('findContact', Gateway::operation(new FindContact($edf)));
        $this->expectException(NotSupportedException::class);
        (new Gateway('u', 'U', new Capabilities(), []))->findContact($edf);
    }

    public function testAStatusIsDeclaredWithTheTrackingIdOfItsMessageForFindFlow(): void
    {
        $gateway = new Gateway('t', 'T', new Capabilities(), [new LookupAction()]);
        $change = new StatusChange(LifecycleStatus::REFUSED, 'F-2026-042', reason: 'Not ordered');

        self::assertSame('status:F-2026-042:1', $gateway->setStatus($change, null, 'status:F-2026-042:1')->trackingId);
        self::assertNull($gateway->setStatus($change)->trackingId);
        self::assertNull((new SetStatus($change))->trackingId, 'a request built before 1.3 still is');
        self::assertSame('setStatus', Gateway::operation(new SetStatus($change, trackingId: 'k')));
    }

    public function testAnInboxIsReadByKindsWhatThePlatformDidNotFilterLeftOut(): void
    {
        $gateway = new Gateway('t', 'T', new Capabilities(), [new LookupAction()]);

        self::assertCount(2, $gateway->receive()->items);
        $statuses = $gateway->receive('c1', 25, [FlowKind::LIFECYCLE]);
        self::assertSame([FlowKind::LIFECYCLE], array_map(static fn (Received $r) => $r->flow->kind, $statuses->items));
        self::assertSame(['c2', true], [$statuses->cursor, $statuses->more]);
        self::assertTrue((new Receive())->wants(FlowKind::REPORT));
        self::assertFalse((new Receive(kinds: [FlowKind::INVOICE]))->wants(FlowKind::LIFECYCLE));
    }

    public function testOneGatewayActsForSeveralAccountsEachBuiltOnce(): void
    {
        $registry = new Registry([new StubFactory()], ['platform' => ['factory' => 'stub', 'options' => ['host' => 'pa.example']]]);
        $first = $registry->forAccount('platform', 'seller-1', ['host' => 'seller-1.pa.example']);

        self::assertSame($first, $registry->forAccount('platform', 'seller-1', ['host' => 'seller-1.pa.example']));
        self::assertNotSame($first, $registry->forAccount('platform', 'seller-2', ['host' => 'seller-2.pa.example']));
        self::assertNotSame($first, $registry->get('platform'));
        self::assertStringStartsWith('seller-1.pa.example/', $first->submit(new \Omnibill\Model\Invoice('<x/>', 'x.xml', \Omnibill\Model\Syntax::CII))->reference);
        self::assertStringStartsWith('pa.example/', $registry->get('platform')->submit(new \Omnibill\Model\Invoice('<x/>', 'x.xml', \Omnibill\Model\Syntax::CII))->reference);
    }

    public function testThePaidStatusCarriesItsAmountsByRateAndTheAddressingItsScheme(): void
    {
        $paid = new StatusChange(LifecycleStatus::PAID, 'F-2026-042', amount: '1250.00', amounts: [2000 => '1200.00', 550 => '50.00']);
        $written = $paid->withFile('<CDAR/>', 'CDV-212-F-2026-042.xml');

        self::assertSame([[2000 => '1200.00', 550 => '50.00'], '<CDAR/>', 'CDV-212-F-2026-042.xml', '1250.00'], [$written->amounts, $written->file, $written->filename, $written->amount]);
        self::assertSame([], (new StatusChange(LifecycleStatus::REFUSED))->amounts);
        self::assertSame('0225', (new Addressing('702042755'))->scheme);
        self::assertSame('0088', (new Addressing('702042755', scheme: '0088'))->scheme);
    }

    public function testEReportingIsDataTheApplicationsWriterTurnsIntoAFile(): void
    {
        $day = new \DateTimeImmutable('2026-11-03');
        $data = new EReportData('702042755', new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-11-10'), [
            new Transaction(TransactionKind::B2C, $day, [new RateTotal(2000, 10000, 2000), new RateTotal(550, 1000, 55)], 'goods'),
            new Transaction(TransactionKind::INTERNATIONAL_SALE, $day, [new RateTotal(0, 50000)], 'services', 'F-2026-050'),
        ], [new Payment(TransactionKind::B2C, $day, [2000 => 12000])]);

        self::assertFalse($data->isEmpty());
        self::assertSame([11000, 2055], [$data->transactionsOf(TransactionKind::B2C)[0]->base(), $data->transactionsOf(TransactionKind::B2C)[0]->vat()]);
        self::assertSame('F-2026-050', $data->transactionsOf(TransactionKind::INTERNATIONAL_SALE)[0]->invoiceNumber);
        self::assertSame(12000, $data->paymentsOf(TransactionKind::B2C)[0]->total());
        self::assertSame([], $data->paymentsOf(TransactionKind::INTERNATIONAL_PURCHASE));
        self::assertTrue((new EReportData('702042755', $day, $day))->isEmpty());
    }
}

/**
 * A platform that finds flow "order-1" and contact "supplier:edf", has a
 * mixed inbox, its search filtering nothing, and carries a status as a flow
 * with the trackingId given.
 */
final class LookupAction implements ActionInterface
{
    public function supports(Request $request): bool
    {
        return $request instanceof FindFlow || $request instanceof Receive || $request instanceof FindContact || $request instanceof SetStatus;
    }

    public function execute(Request $request): void
    {
        if ($request instanceof FindFlow) {
            $request->setResult('order-1' === $request->trackingId ? new Flow('F-1', trackingId: 'order-1') : null);
        } elseif ($request instanceof FindContact) {
            $request->setResult('supplier:edf' === $request->contact->key ? $request->contact->with(reference: 'P-7') : null);
        } elseif ($request instanceof SetStatus) {
            $request->setResult(new Flow('LC-2', FlowKind::LIFECYCLE, trackingId: $request->trackingId));
        } elseif ($request instanceof Receive) {
            $request->setResult(new Inbox([new Received(new Flow('IN-1', FlowKind::INVOICE)), new Received(new Flow('LC-1', FlowKind::LIFECYCLE))], null === $request->cursor ? 'c1' : 'c2', true));
        }
    }
}
