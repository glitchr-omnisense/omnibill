<?php

namespace Omnibill\Model;

/** Where an entry stands in the accounting software. */
enum EntryStatus: string
{
    /** Recorded, still editable. */
    case DRAFT = 'draft';
    /** Validated: numbered, immutable - a mistake is undone by a reversal. */
    case POSTED = 'posted';
    case CANCELED = 'canceled';
    case UNKNOWN = 'unknown';
}
