<?php

namespace Omnibill\Model;

/**
 * What a VAT line does in the VAT return - the same cases as
 * omnibase/ledger's TaxKind, by value: VAT collected on sales, deductible
 * on goods and services or on fixed assets, a reverse charge, an exempt
 * operation (no VAT, its base reported).
 */
enum VatKind: string
{
    case COLLECTED = 'collected';
    case DEDUCTIBLE = 'deductible';
    case DEDUCTIBLE_ASSETS = 'deductible_assets';
    case REVERSE_CHARGE = 'reverse_charge';
    case EXEMPT = 'exempt';
}
