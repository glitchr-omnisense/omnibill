<?php

namespace Omnibill\Model;

enum Direction: string
{
    /** Sent by the application */
    case OUT = 'out';
    /** Received by the application */
    case IN = 'in';
}
