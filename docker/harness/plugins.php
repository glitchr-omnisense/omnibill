<?php

/**
 * Every gateway package: its slug (omnibill/<slug>, github.com/glitchr-omnisense/omnibill-<slug>),
 * its tests' namespace and its factory class.
 */
return [
    'email' => ['Omnibill\\Email\\Tests\\', 'Omnibill\\Email\\EmailGatewayFactory'],
    'afnor' => ['Omnibill\\Afnor\\Tests\\', 'Omnibill\\Afnor\\AfnorGatewayFactory'],
    'chorus-pro' => ['Omnibill\\ChorusPro\\Tests\\', 'Omnibill\\ChorusPro\\ChorusProGatewayFactory'],
];
