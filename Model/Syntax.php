<?php

namespace Omnibill\Model;

/**
 * The syntax of a file a gateway carries - the names XP Z12-013's Flow
 * Service gives them. The family reads none: a file arrives conformant.
 */
enum Syntax: string
{
    /** A PDF/A-3 with its CII XML inside (Factur-X, ZUGFeRD) */
    case FACTURX = 'Factur-X';
    /** UBL 2.1 */
    case UBL = 'UBL';
    /** UN/CEFACT Cross Industry Invoice */
    case CII = 'CII';
    /** UN/CEFACT Cross Domain Acknowledgement and Response: a lifecycle status */
    case CDAR = 'CDAR';
    /** An e-reporting file (transactions, payments) */
    case FRR = 'FRR';

    public function mimeType(): string
    {
        return self::FACTURX === $this ? 'application/pdf' : 'application/xml';
    }

    public function extension(): string
    {
        return self::FACTURX === $this ? 'pdf' : 'xml';
    }
}
