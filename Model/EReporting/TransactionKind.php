<?php

namespace Omnibill\Model\EReporting;

/**
 * What e-reporting covers, in the words of the DGFiP's public summary
 * (impots.gouv.fr, "e-reporting"): the transactions that are no domestic
 * e-invoice - sales to private persons, sales to and purchases from
 * companies established abroad.
 */
enum TransactionKind: string
{
    /** A sale to a private person (B2C), usually aggregated by day */
    case B2C = 'b2c';
    /** A sale to a company established abroad (B2B international), invoice by invoice */
    case INTERNATIONAL_SALE = 'international_sale';
    /** A purchase from a company established abroad, invoice by invoice */
    case INTERNATIONAL_PURCHASE = 'international_purchase';
}
