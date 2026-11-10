<?php

namespace Omnibill\Model;

/** A contact of the books: one who is invoiced (411), one who invoices (401). */
enum ContactKind: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';
}
