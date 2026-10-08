<?php

namespace Omnibill;

use Omnibill\Model\Addressing;
use Omnibill\Model\Capabilities;
use Omnibill\Model\EReport;
use Omnibill\Model\Flow;
use Omnibill\Model\Inbox;
use Omnibill\Model\Invoice;
use Omnibill\Model\Notification;
use Omnibill\Model\StatusChange;
use Omnibill\Request\Request;

/**
 * One way to transmit and receive electronic invoices, configured - an
 * e-mail, an approved platform, the public sector's portal: the same
 * questions for all of them. Each typed method is a shortcut for execute()
 * with its request; a gateway that does not do something throws
 * NotSupportedException, and supports() says so beforehand.
 *
 * A gateway carries files already conformant. It produces none, reads none
 * beyond what it must to route them (a lifecycle message's status), and
 * keeps no books: the application does.
 */
interface GatewayInterface
{
    public function getName(): string;

    public function getTitle(): string;

    /** What it carries, and whether it is an approved platform's channel. */
    public function capabilities(): Capabilities;

    /** @param class-string<Request> $request */
    public function supports(string $request): bool;

    /**
     * @template T of Request
     *
     * @param T $request
     *
     * @return T answered
     *
     * @throws Exception\NotSupportedException
     * @throws Exception\ProviderException
     */
    public function execute(Request $request): Request;

    /** Transmit an invoice: its flow, to keep. */
    public function submit(Invoice $invoice, ?string $trackingId = null): Flow;

    /** The flow as the platform has it now. */
    public function fetchStatus(Flow $flow): Flow;

    /** What arrived since the cursor: invoices, statuses. */
    public function receive(?string $cursor = null, int $limit = 25): Inbox;

    /** Declare a status: paid, refused, approved... */
    public function setStatus(StatusChange $change, ?Flow $flow = null): Flow;

    /**
     * Where a company receives its invoices (SIREN or SIRET).
     *
     * @return list<Addressing>
     */
    public function lookup(string $identifier): array;

    /** Transmit an e-reporting file. */
    public function report(EReport $report, ?string $trackingId = null): Flow;

    /**
     * What the platform is telling us (a webhook): checked against its
     * signature, read as one event.
     *
     * @param array<string, string|string[]> $headers
     *
     * @throws Exception\InvalidNotificationException when the signature does not hold
     */
    public function notify(string $body, array $headers = []): Notification;
}
