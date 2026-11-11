<?php

namespace Omnibill\Writer;

use Omnibill\Model\Flow;
use Omnibill\Model\StatusChange;

/**
 * Since 1.2, an extension point the application implements: a status
 * declared (taken over, approved, refused with its reason, paid with its
 * amounts by rate...) written as its lifecycle message (CDAR), the file an
 * approved platform carries - StatusChange::withFile(). The family ships no
 * implementation: the message's content is in XP Z12-012/013 and the DGFiP's
 * external specifications' annexes (Annex 2, flux 6), whose notice forbids
 * their processing by AI - a person writes this class, or validates it,
 * against them. The application finds the parties and the invoice's date
 * from the invoice's number it keeps. See docs/usage-statuses.md.
 */
interface LifecycleMessageWriterInterface
{
    /** The same change, with its file (and its filename). */
    public function write(StatusChange $change, ?Flow $flow = null): StatusChange;
}
