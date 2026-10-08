<?php

/**
 * The gateways, their options from the environment (.env): a gateway is
 * configured only when every key under "needs" is set. "example" is what
 * the bare script uses instead - the platforms then answer from their
 * packages' recorded answers.
 *
 * @return array<string, array{factory: string, needs: list<string>, options: array<string, mixed>, example: array<string, mixed>}>
 */
$env = static fn (string $key, mixed $default = null): mixed => (false !== ($v = getenv($key)) && '' !== $v) ? $v : $default;

return [
    'email' => [
        'factory' => 'email',
        'needs' => ['INVOICE_FROM'],
        'options' => ['from' => $env('INVOICE_FROM')],
        'example' => ['from' => 'Atelier Tilleul <factures@atelier.example>'],
    ],
    'afnor' => [
        'factory' => 'afnor',
        'needs' => ['PA_FLOW_URL', 'PA_TOKEN_URL', 'PA_CLIENT_ID', 'PA_CLIENT_SECRET'],
        'options' => ['flow_url' => $env('PA_FLOW_URL'), 'directory_url' => $env('PA_DIRECTORY_URL'), 'token_url' => $env('PA_TOKEN_URL'), 'client_id' => $env('PA_CLIENT_ID'), 'client_secret' => $env('PA_CLIENT_SECRET'), 'webhook_key' => $env('PA_WEBHOOK_KEY')],
        'example' => ['flow_url' => 'https://api.flow.pa.example/flow-service', 'directory_url' => 'https://api.directory.pa.example/directory-service', 'token_url' => 'https://auth.pa.example/auth-service/acme/token', 'client_id' => 'acme', 'client_secret' => 'example'],
    ],
    'chorus-pro' => [
        'factory' => 'chorus-pro',
        'needs' => ['PISTE_CLIENT_ID', 'PISTE_CLIENT_SECRET', 'CPRO_LOGIN', 'CPRO_PASSWORD'],
        'options' => ['client_id' => $env('PISTE_CLIENT_ID'), 'client_secret' => $env('PISTE_CLIENT_SECRET'), 'login' => $env('CPRO_LOGIN'), 'password' => $env('CPRO_PASSWORD'), 'sandbox' => $env('CPRO_SANDBOX', '1')],
        'example' => ['client_id' => 'piste-app', 'client_secret' => 'example', 'login' => 'TECH_1_atelier@cpro.fr', 'password' => 'example', 'sandbox' => true],
    ],
];
