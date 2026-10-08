<?php

namespace Omnibill\Tests;

use Omnibill\Model\Addressing;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Flow;
use Omnibill\Model\FlowState;
use Omnibill\Model\Inbox;
use Omnibill\Model\Invoice;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\Received;
use Omnibill\Model\StatusChange;
use Omnibill\Model\Syntax;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testTheLifecycleStatusesAreTheReformsWithTheirCodes(): void
    {
        self::assertSame(range(200, 213), array_values(array_filter(array_map(static fn (LifecycleStatus $s) => $s->code(), LifecycleStatus::cases()))));
        self::assertSame(['Déposée', 'Refusée', 'Encaissée', 'Rejetée'], array_map(static fn (LifecycleStatus $s) => $s->label(), array_values(array_filter(LifecycleStatus::cases(), static fn (LifecycleStatus $s) => $s->isMandatory()))));
        self::assertSame(LifecycleStatus::PAID, LifecycleStatus::fromCode('fr:212'));
        self::assertSame(LifecycleStatus::DISPUTED, LifecycleStatus::fromCode(207));
        self::assertNull(LifecycleStatus::fromCode('299'));
        self::assertNull(LifecycleStatus::SENT->code());
        self::assertTrue(LifecycleStatus::REFUSED->isFinal());
        self::assertFalse(LifecycleStatus::SUSPENDED->isFinal());
    }

    public function testAnInvoiceIsAFileAndWhatTheApplicationKnowsOfIt(): void
    {
        $path = sys_get_temp_dir().'/omnibill-'.bin2hex(random_bytes(4)).'.pdf';
        file_put_contents($path, '%PDF-1.7 a Factur-X');
        try {
            $invoice = Invoice::fromFile($path, number: 'F-1');
            self::assertSame([Syntax::FACTURX, 'F-1', 19, hash('sha256', '%PDF-1.7 a Factur-X')], [$invoice->syntax, $invoice->number, $invoice->size(), $invoice->sha256()]);
            self::assertSame(['application/pdf', 'pdf'], [$invoice->syntax->mimeType(), $invoice->syntax->extension()]);
            self::assertSame(Syntax::CII, Invoice::fromFile(__FILE__)->syntax, 'not a PDF: CII, unless told');
        } finally {
            @unlink($path);
        }
        $this->expectException(\InvalidArgumentException::class);
        Invoice::fromFile('/nowhere/invoice.pdf');
    }

    public function testAFlowIsUpdatedNotChanged(): void
    {
        $flow = new Flow('f1', invoiceNumber: 'F-1');
        $later = $flow->with(state: FlowState::ACCEPTED, status: LifecycleStatus::DEPOSITED, history: [new StatusChange(LifecycleStatus::DEPOSITED)]);

        self::assertSame([FlowState::PENDING, null], [$flow->state, $flow->status]);
        self::assertSame([FlowState::ACCEPTED, LifecycleStatus::DEPOSITED, 'f1', 'F-1'], [$later->state, $later->status, $later->reference, $later->invoiceNumber]);
    }

    public function testAnInboxSortsInvoicesFromStatuses(): void
    {
        $inbox = new Inbox([new Received(new Flow('a'), new Invoice('x', 'a.xml', Syntax::UBL)), new Received(new Flow('b'), status: new StatusChange(LifecycleStatus::APPROVED, 'F-1'))], 'cursor-2', true);

        self::assertCount(1, $inbox->invoices());
        self::assertSame(LifecycleStatus::APPROVED, $inbox->statuses()[0]->status);
        self::assertTrue($inbox->items[0]->isInvoice());
        self::assertTrue((new Addressing('702042755'))->isEnabled());
        self::assertFalse((new Addressing('702042755', status: 'Disabled'))->isEnabled());
        self::assertSame(['Factur-X', 'UBL', 'CII'], (new Capabilities())->toArray()['syntaxes']);
        self::assertFalse((new Capabilities())->carries(Syntax::CDAR));
    }
}
