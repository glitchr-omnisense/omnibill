<?php

namespace Omnibill\Model;

/** What a flow carries. */
enum FlowKind: string
{
    case INVOICE = 'invoice';
    case LIFECYCLE = 'lifecycle';
    case REPORT = 'report';
    case OTHER = 'other';
}
