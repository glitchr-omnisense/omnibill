<?php

namespace Omnibill\Tests;

use Omnibill\Bridge\Symfony\OmnibillBundle;
use Omnibill\Exception\NotSupportedException;
use Omnibill\JournalEntriesInterface;
use Omnibill\Model\Amount;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Contact;
use Omnibill\Model\ContactKind;
use Omnibill\Model\EntryLine;
use Omnibill\Model\JournalEntry;
use Omnibill\Model\Vat;
use Omnibill\Model\VatKind;
use Omnibill\Registry;
use Omnibill\Request\Accounting\PostEntry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;

/** The journal-entries and contacts contracts (1.1), beside the e-invoices', which stays as it was. */
final class AccountingContractsTest extends TestCase
{
    public function testAGatewayBuiltBefore11KeepsTheEInvoicesOnly(): void
    {
        $capabilities = new Capabilities();
        self::assertSame([Capabilities::E_INVOICES], $capabilities->contracts);
        self::assertFalse($capabilities->keeps(Capabilities::JOURNAL_ENTRIES));
        self::assertNull($capabilities->toArray()['terms']);

        $registry = new Registry([new StubFactory()], ['pa' => ['factory' => 'stub', 'options' => ['host' => 'h']]]);
        $gateway = $registry->get('pa');
        self::assertInstanceOf(JournalEntriesInterface::class, $gateway, 'the door is there');
        self::assertFalse($gateway->supports(PostEntry::class), 'no action behind it');
        try {
            $registry->journalEntries('pa');
            self::fail();
        } catch (NotSupportedException $e) {
            self::assertStringContainsString('posts no journal entries', $e->getMessage());
        }
        $this->expectException(NotSupportedException::class);
        $registry->contacts('pa');
    }

    public function testAnEntryMapsFromTheLedgersDraft(): void
    {
        // omnibase/ledger: (new EntryDraft($book, 'VT', $date, 'Loyer octobre U3', 'notice:2026-10-U3', 'AVIS-2026-10-U3'))
        //     ->line('411U3', 85000, 0, $tenant)->line('706', 0, 85000)
        $entry = new JournalEntry('VT', new \DateTimeImmutable('2026-10-01'), 'Loyer octobre U3', [
            new EntryLine('411U3', 85000, 0, contact: 'lease-42'),
            new EntryLine('706', 0, 85000),
        ], 'AVIS-2026-10-U3', 'notice:2026-10-U3');

        self::assertTrue($entry->isBalanced());
        self::assertSame([85000, 85000], [$entry->totalDebit(), $entry->totalCredit()]);
        self::assertSame([85000, -85000], array_map(static fn (EntryLine $l) => $l->net(), $entry->lines));
        self::assertFalse((new JournalEntry('OD', new \DateTimeImmutable(), 'x'))->isBalanced(), 'no line, no entry');

        $posted = $entry->with(reference: '42', number: 'VT-0001');
        self::assertSame(['42', 'VT-0001', 'notice:2026-10-U3', 'VT'], [$posted->reference(), $posted->number, $posted->key, $posted->journal]);
        $this->expectException(\LogicException::class);
        $entry->reference();
    }

    public function testVatAndAmounts(): void
    {
        self::assertSame(['20', '5.5', '2.1', '0'], array_map(static fn (int $r) => (new Vat($r))->percent(), [2000, 550, 210, 0]));
        self::assertSame(VatKind::REVERSE_CHARGE, VatKind::from('reverse_charge'), 'the ledger\'s TaxKind values');
        self::assertSame(['120.50', '0.00', '-3.07', '1234567.89'], array_map(Amount::decimal(...), [12050, 0, -307, 123456789]));
        self::assertSame([12050, 12050, 12000, 12000, -307, 1, 30], [Amount::minor('120.5'), Amount::minor('120.50'), Amount::minor(120), Amount::minor(120.0), Amount::minor('-3.07'), Amount::minor('0.005'), Amount::minor(0.1 + 0.2)]);
        $this->expectException(\InvalidArgumentException::class);
        Amount::minor('12,50');
    }

    public function testAContactMapsFromTheLedgersParty(): void
    {
        // omnibase/ledger: new Party($book, PartyKind::TENANT, 'Camille Érable', '411U3', 'lease-42', 'FR76...')
        $contact = new Contact(ContactKind::CUSTOMER, 'Camille Érable', 'lease-42', '411U3', iban: 'FR7630004000031234567890143', company: false);
        $saved = $contact->with(reference: '2201');

        self::assertSame(['2201', 'lease-42', '411U3', false], [$saved->reference(), $saved->key, $saved->accountNumber, $saved->company]);
    }

    public function testTheBundleInjectsAGatewayByItsContract(): void
    {
        if (!class_exists(\Omnibill\Odoo\OdooGatewayFactory::class)) {
            self::markTestSkipped('omnibill/odoo is not installed.');
        }
        $container = new ContainerBuilder();
        $container->register('http_client', MockHttpClient::class);
        $container->register(Accountant::class)->setAutowired(true)->setPublic(true);
        $container->registerExtension((new OmnibillBundle())->getContainerExtension() ?? throw new \LogicException());
        $container->loadFromExtension('omnibill', ['gateways' => ['books' => ['factory' => 'odoo', 'options' => ['url' => 'https://odoo.example', 'api_key' => 'k']]]]);
        $container->compile();

        $accountant = $container->get(Accountant::class);
        self::assertInstanceOf(Accountant::class, $accountant);
        self::assertInstanceOf(\Omnibill\GatewayInterface::class, $accountant->books);
        self::assertTrue($accountant->books->capabilities()->keeps(Capabilities::JOURNAL_ENTRIES));
    }
}

final class Accountant
{
    public function __construct(public readonly JournalEntriesInterface $books)
    {
    }
}
