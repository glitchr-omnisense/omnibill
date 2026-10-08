<?php

namespace Omnibill\Action;

use Omnibill\Request\Request;

/** One thing a gateway does: answers the requests it supports. */
interface ActionInterface
{
    public function supports(Request $request): bool;

    /** Sets the request's result, or throws an Omnibill\Exception\OmnibillException. */
    public function execute(Request $request): void;
}
