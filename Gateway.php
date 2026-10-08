<?php

namespace Omnibill;

use Omnibill\Action\ActionInterface;
use Omnibill\Exception\NotSupportedException;
use Omnibill\Exception\ProviderException;
use Omnibill\Model\Capabilities;
use Omnibill\Model\EReport;
use Omnibill\Model\Flow;
use Omnibill\Model\Inbox;
use Omnibill\Model\Invoice;
use Omnibill\Model\Notification;
use Omnibill\Model\StatusChange;

/** A gateway's actions behind one door: the first action supporting a request answers it. */
final class Gateway implements GatewayInterface
{
    /** @param ActionInterface[] $actions */
    public function __construct(
        private readonly string $name,
        private readonly string $title,
        private readonly Capabilities $capabilities,
        private readonly array $actions,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function capabilities(): Capabilities
    {
        return $this->capabilities;
    }

    public function supports(string $request): bool
    {
        $probe = (new \ReflectionClass($request))->newInstanceWithoutConstructor();
        foreach ($this->actions as $action) {
            if ($action->supports($probe)) {
                return true;
            }
        }

        return false;
    }

    public function execute(Request\Request $request): Request\Request
    {
        foreach ($this->actions as $action) {
            if ($action->supports($request)) {
                $action->execute($request);
                if (!$request->isAnswered()) {
                    throw new ProviderException($this->name, \sprintf('%s left the request unanswered.', $action::class));
                }

                return $request;
            }
        }

        throw NotSupportedException::for($request, $this->name);
    }

    public function submit(Invoice $invoice, ?string $trackingId = null): Flow
    {
        return $this->execute(new Request\Submit($invoice, $trackingId))->getFlow();
    }

    public function fetchStatus(Flow $flow): Flow
    {
        return $this->execute(new Request\FetchStatus($flow))->getFlow();
    }

    public function receive(?string $cursor = null, int $limit = 25): Inbox
    {
        return $this->execute(new Request\Receive($cursor, $limit))->getInbox();
    }

    public function setStatus(StatusChange $change, ?Flow $flow = null): Flow
    {
        return $this->execute(new Request\SetStatus($change, $flow))->getFlow();
    }

    public function lookup(string $identifier): array
    {
        return $this->execute(new Request\Lookup($identifier))->getAddressings();
    }

    public function report(EReport $report, ?string $trackingId = null): Flow
    {
        return $this->execute(new Request\Report($report, $trackingId))->getFlow();
    }

    public function notify(string $body, array $headers = []): Notification
    {
        return $this->execute(new Request\Notify($body, $headers))->getNotification();
    }
}
