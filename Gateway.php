<?php

namespace Omnibill;

use Omnibill\Action\ActionInterface;
use Omnibill\Exception\NotSupportedException;
use Omnibill\Exception\ProviderException;
use Omnibill\Model\Capabilities;
use Omnibill\Model\Contact;
use Omnibill\Model\ContactKind;
use Omnibill\Model\ContactList;
use Omnibill\Model\EntryList;
use Omnibill\Model\JournalEntry;
use Omnibill\Model\EReport;
use Omnibill\Model\Flow;
use Omnibill\Model\FlowKind;
use Omnibill\Model\Inbox;
use Omnibill\Model\Invoice;
use Omnibill\Model\Notification;
use Omnibill\Model\StatusChange;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A gateway's actions behind one door: the first action supporting a
 * request answers it. Since 1.1 the door has the journal-entries and
 * contacts contracts too; a gateway answers those its actions support.
 * Since 1.2, findFlow() and findEntry() (FlowLookupInterface,
 * EntryLookupInterface): a write found again by the application's key, and
 * receive() takes the kinds of flows wanted (Request\Receive::$kinds): what
 * the platform does not filter, the gateway leaves out of the Inbox.
 * Since 1.3, findContact() (ContactLookupInterface): a contact found again
 * by the application's key, and setStatus() takes the trackingId of the
 * message carrying the status (Request\SetStatus::$trackingId), for
 * findFlow(). GatewayInterface keeps its signatures.
 *
 * Built by a GatewayFactory, it runs behind the provider's PolicyGuard: each
 * request that reaches the provider is an operation named after its typed
 * method (submit, fetchStatus, receive, setStatus, lookup, report, findFlow,
 * postEntry, fetchEntry, entries, findEntry, saveContact, fetchContact, contacts,
 * findContact; a
 * request of the application's own, its short name with a lower-case
 * initial), checked against the policy's rate limits and the provider's
 * wait before, counted after; Exception\UsageLimitReachedException refuses
 * it before it is made. A webhook read (Notify) reaches nothing and is not
 * counted.
 */
final class Gateway implements GatewayInterface, JournalEntriesInterface, ContactsInterface, FlowLookupInterface, EntryLookupInterface, ContactLookupInterface
{
    /** The operation each request is, for the guard: its typed method's name; null for what reaches no provider. */
    private const OPERATIONS = [
        Request\Submit::class => 'submit',
        Request\FetchStatus::class => 'fetchStatus',
        Request\Receive::class => 'receive',
        Request\SetStatus::class => 'setStatus',
        Request\Lookup::class => 'lookup',
        Request\Report::class => 'report',
        Request\FindFlow::class => 'findFlow',
        Request\Notify::class => null,
        Request\Accounting\PostEntry::class => 'postEntry',
        Request\Accounting\FetchEntry::class => 'fetchEntry',
        Request\Accounting\ListEntries::class => 'entries',
        Request\Accounting\FindEntry::class => 'findEntry',
        Request\Accounting\SaveContact::class => 'saveContact',
        Request\Accounting\FetchContact::class => 'fetchContact',
        Request\Accounting\ListContacts::class => 'contacts',
        Request\Accounting\FindContact::class => 'findContact',
    ];

    private ?PolicyGuard $guard = null;

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

    /**
     * The provider's guard: its usage policy as the site runs it, the
     * limiter's counts; null for a gateway built by hand.
     */
    public function guard(): ?PolicyGuard
    {
        return $this->guard;
    }

    /**
     * @internal the factory hands the gateway it built its guard, once
     */
    public function guardWith(PolicyGuard $guard): void
    {
        $this->guard ??= $guard;
    }

    /** The operation a request is, for the guard; null when it reaches no provider. */
    public static function operation(Request\Request $request): ?string
    {
        return \array_key_exists($request::class, self::OPERATIONS) ? self::OPERATIONS[$request::class] : lcfirst((new \ReflectionClass($request))->getShortName());
    }

    public function execute(Request\Request $request): Request\Request
    {
        foreach ($this->actions as $action) {
            if ($action->supports($request)) {
                $operation = null === $this->guard ? null : self::operation($request);
                if (null === $this->guard || null === $operation) {
                    $action->execute($request);
                } else {
                    $this->guard->call($operation, static fn () => $action->execute($request));
                }
                if (!$request->isAnswered()) {
                    throw new ProviderException($this->name, \sprintf('%s left the request unanswered.', $action::class));
                }
                if ($request instanceof Request\Receive && [] !== $request->kinds && null !== ($inbox = $request->getInbox())) {
                    $request->setResult($inbox->only($request->kinds));
                }

                return $request;
            }
        }

        throw NotSupportedException::for($request, $this->name);
    }

    public function submit(Invoice $invoice, ?string $trackingId = null): Flow
    {
        return $this->execute(new Request\Submit($invoice, $trackingId))->getFlow() ?? throw new ProviderException($this->name, 'No flow answered.');
    }

    public function fetchStatus(Flow $flow): Flow
    {
        return $this->execute(new Request\FetchStatus($flow))->getFlow() ?? throw new ProviderException($this->name, 'No flow answered.');
    }

    /** @param list<FlowKind> $kinds the kinds of flows wanted (since 1.2); none: all */
    public function receive(?string $cursor = null, int $limit = 25, array $kinds = []): Inbox
    {
        return $this->execute(new Request\Receive($cursor, $limit, $kinds))->getInbox() ?? throw new ProviderException($this->name, 'No inbox answered.');
    }

    public function findFlow(string $trackingId): ?Flow
    {
        return $this->execute(new Request\FindFlow($trackingId))->getFlow();
    }

    public function findEntry(JournalEntry $entry): ?JournalEntry
    {
        return $this->execute(new Request\Accounting\FindEntry($entry))->getEntry();
    }

    /** @param string|null $trackingId the application's key for the message carrying the status (since 1.3), for findFlow() */
    public function setStatus(StatusChange $change, ?Flow $flow = null, ?string $trackingId = null): Flow
    {
        return $this->execute(new Request\SetStatus($change, $flow, $trackingId))->getFlow() ?? throw new ProviderException($this->name, 'No flow answered.');
    }

    public function lookup(string $identifier): array
    {
        return $this->execute(new Request\Lookup($identifier))->getAddressings() ?? throw new ProviderException($this->name, 'No addressings answered.');
    }

    public function report(EReport $report, ?string $trackingId = null): Flow
    {
        return $this->execute(new Request\Report($report, $trackingId))->getFlow() ?? throw new ProviderException($this->name, 'No flow answered.');
    }

    public function notify(string $body, array $headers = []): Notification
    {
        return $this->execute(new Request\Notify($body, $headers))->getNotification() ?? throw new ProviderException($this->name, 'No notification answered.');
    }

    public function postEntry(JournalEntry $entry): JournalEntry
    {
        return $this->execute(new Request\Accounting\PostEntry($entry))->getEntry() ?? throw new ProviderException($this->name, 'No entry answered.');
    }

    public function fetchEntry(JournalEntry $entry): JournalEntry
    {
        return $this->execute(new Request\Accounting\FetchEntry($entry))->getEntry() ?? throw new ProviderException($this->name, 'No entry answered.');
    }

    public function entries(?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?string $journal = null, ?string $cursor = null, int $limit = 25): EntryList
    {
        return $this->execute(new Request\Accounting\ListEntries($from, $to, $journal, $cursor, $limit))->getEntries() ?? throw new ProviderException($this->name, 'No entries answered.');
    }

    public function saveContact(Contact $contact): Contact
    {
        return $this->execute(new Request\Accounting\SaveContact($contact))->getContact() ?? throw new ProviderException($this->name, 'No contact answered.');
    }

    public function fetchContact(Contact $contact): Contact
    {
        return $this->execute(new Request\Accounting\FetchContact($contact))->getContact() ?? throw new ProviderException($this->name, 'No contact answered.');
    }

    public function findContact(Contact $contact): ?Contact
    {
        return $this->execute(new Request\Accounting\FindContact($contact))->getContact();
    }

    public function contacts(ContactKind $kind, ?string $search = null, ?string $cursor = null, int $limit = 25): ContactList
    {
        return $this->execute(new Request\Accounting\ListContacts($kind, $search, $cursor, $limit))->getContacts() ?? throw new ProviderException($this->name, 'No contacts answered.');
    }
}
