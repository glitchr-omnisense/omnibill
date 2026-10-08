<?php

namespace Omnibill\Model;

/**
 * Whether the platform took the file - its technical acknowledgement, not
 * what the recipient did with the invoice (LifecycleStatus).
 */
enum FlowState: string
{
    /** Received, not checked yet */
    case PENDING = 'pending';
    /** Checked and taken */
    case ACCEPTED = 'accepted';
    /** Refused by the platform's checks: see the flow's errors */
    case REJECTED = 'rejected';
}
