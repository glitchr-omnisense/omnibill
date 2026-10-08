<?php

namespace Omnibill\Model;

enum Event: string
{
    /** A flow was taken or rejected by the platform */
    case FLOW_UPDATED = 'flow_updated';
    /** Something arrived for the application: an invoice, a status */
    case RECEIVED = 'received';
    case OTHER = 'other';
}
