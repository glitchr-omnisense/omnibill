<?php

namespace Omnibill\Tests;

use Omnibill\Action\ActionInterface;
use Omnibill\Action\ApiAwareInterface;
use Omnibill\Action\ApiAwareTrait;
use Omnibill\Config;
use Omnibill\GatewayFactory;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Flow;
use Omnibill\Model\LifecycleStatus;
use Omnibill\Model\StatusChange;
use Omnibill\Request\FetchStatus;
use Omnibill\Request\Request;
use Omnibill\Request\SetStatus;
use Omnibill\Request\Submit;

/**
 * A platform kept in memory, for the tests: its API is the list of the
 * flows it took. It submits, fetches and records statuses; nothing else.
 * "silent: true" leaves out fetch and set; "mute" answers nothing.
 */
final class StubFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnibill.factory_name' => 'stub',
            'omnibill.factory_title' => 'Stub',
            'omnibill.required_options' => ['host'],
            'silent' => false,
            'omnibill.capabilities' => new Capabilities(approved: true),
            'omnibill.api' => static fn (Config $c) => new StubApi((string) $c['host']),
            'omnibill.action.submit' => new StubAction(Submit::class),
            'omnibill.action.fetch' => static fn (Config $c) => $c['silent'] ? null : new StubAction(FetchStatus::class),
            'omnibill.action.set' => static fn (Config $c) => $c['silent'] ? null : new StubAction(SetStatus::class),
            'omnibill.action.mute' => new MuteAction(),
        ]);
    }
}

final class StubApi
{
    /** @var array<string, list<StatusChange>> */
    public array $flows = [];

    public function __construct(public readonly string $host)
    {
    }
}

final class StubAction implements ActionInterface, ApiAwareInterface
{
    /** @use ApiAwareTrait<StubApi> */
    use ApiAwareTrait;

    /** @param class-string<Request> $request */
    public function __construct(private readonly string $request)
    {
        $this->apiClass = StubApi::class;
    }

    public function supports(Request $request): bool
    {
        return $request instanceof $this->request;
    }

    public function execute(Request $request): void
    {
        if ($request instanceof Submit) {
            $reference = $this->api->host.'/'.substr(sha1($request->invoice->content), 0, 8);
            $this->api->flows[$reference] = [new StatusChange(LifecycleStatus::DEPOSITED, $request->invoice->number)];
            $request->setResult(new Flow($reference, invoiceNumber: $request->invoice->number, status: LifecycleStatus::DEPOSITED, history: $this->api->flows[$reference]));
        } elseif ($request instanceof FetchStatus) {
            $history = $this->api->flows[$request->flow->reference] ?? [];
            $request->setResult($request->flow->with(status: $history ? $history[array_key_last($history)]->status : null, history: $history));
        } elseif ($request instanceof SetStatus) {
            $this->api->flows[$request->flow->reference][] = $request->change;
            $request->setResult($request->flow->with(status: $request->change->status, history: $this->api->flows[$request->flow->reference]));
        }
    }
}

final class Mute extends Request
{
}

final class MuteAction implements ActionInterface
{
    public function supports(Request $request): bool
    {
        return $request instanceof Mute;
    }

    public function execute(Request $request): void
    {
    }
}
