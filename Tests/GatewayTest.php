<?php

namespace Omnibill\Tests;

use Omnibill\Exception\InvalidConfigException;
use Omnibill\Exception\NotSupportedException;
use Omnibill\Exception\ProviderException;
use Omnibill\Model\Invoice;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\StatusChange;
use Omnibill\Model\Syntax;
use Omnibill\Registry;
use Omnibill\Request\FetchStatus;
use Omnibill\Request\Lookup;
use Omnibill\Request\Receive;
use PHPUnit\Framework\TestCase;

final class GatewayTest extends TestCase
{
    private function registry(): Registry
    {
        return new Registry([new StubFactory()], [
            'platform' => ['factory' => 'stub', 'options' => ['host' => 'pa.example']],
            'quiet' => ['factory' => 'stub', 'options' => ['host' => 'quiet.example', 'silent' => true]],
            'broken' => ['factory' => 'stub'],
            'unknown' => ['factory' => 'nowhere'],
        ]);
    }

    public function testAnInvoiceIsSubmittedFollowedAndPaid(): void
    {
        $gateway = $this->registry()->get('platform');
        $flow = $gateway->submit(new Invoice('<Invoice/>', 'F-2026-001.xml', Syntax::CII, 'F-2026-001'));

        self::assertSame(LifecycleStatus::DEPOSITED, $flow->status);
        $paid = $gateway->setStatus(new StatusChange(LifecycleStatus::PAID, amount: '1200.00'), $flow);
        self::assertSame(LifecycleStatus::PAID, $gateway->fetchStatus($paid)->status);
        self::assertSame([LifecycleStatus::DEPOSITED, LifecycleStatus::PAID], array_map(static fn ($c) => $c->status, $gateway->fetchStatus($flow)->history));
        $registry = $this->registry();
        self::assertSame($registry->get('platform'), $registry->get('platform'), 'built once');
    }

    public function testWhatAGatewayDoesNotDoIsSaidBeforehandAndThrown(): void
    {
        $quiet = $this->registry()->get('quiet');

        self::assertFalse($quiet->supports(FetchStatus::class));
        self::assertFalse($quiet->supports(Receive::class));
        self::assertTrue($this->registry()->get('platform')->supports(FetchStatus::class));
        try {
            $quiet->lookup('702042755');
            self::fail();
        } catch (NotSupportedException $e) {
            self::assertSame('The "stub" gateway does not support Lookup.', $e->getMessage());
        }
        $this->expectException(ProviderException::class);
        $quiet->execute(new Mute());
    }

    public function testMisconfigurationShowsWhenTheGatewayIsBuilt(): void
    {
        foreach (['broken' => 'The "stub" gateway needs: host.', 'unknown' => 'No "nowhere" factory for the "unknown" gateway; installed: stub.', 'none' => 'No "none" gateway; configured: platform, quiet, broken, unknown.'] as $name => $message) {
            try {
                $this->registry()->get($name);
                self::fail($name);
            } catch (InvalidConfigException $e) {
                self::assertSame($message, $e->getMessage());
            }
        }
        self::assertSame('override.example', explode('/', $this->registry()->create('platform', ['host' => 'override.example'])->submit(new Invoice('x', 'x.xml', Syntax::UBL))->reference)[0]);
        self::assertTrue((new \ReflectionClass(Lookup::class))->isFinal());
    }
}
