<?php

namespace Omnibill\Writer;

use Omnibill\Model\EReport;
use Omnibill\Model\EReporting\EReportData;

/**
 * Since 1.2, an extension point the application implements: an
 * e-reporting's data written as the file the platform takes (Syntax::FRR).
 * The family ships no implementation: the format is in the DGFiP's
 * external specifications' annexes (flux 10), whose notice forbids their
 * processing by AI - a person writes this class, or validates it, against
 * them. See docs/e-reporting.md.
 */
interface EReportWriterInterface
{
    public function write(EReportData $data): EReport;
}
