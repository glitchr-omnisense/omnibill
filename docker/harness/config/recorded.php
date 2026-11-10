<?php

/**
 * The platforms as their packages' tests answer them: a MockHttpClient
 * callback serving each call from the Tests/Fixtures of the package that
 * makes it. Nothing leaves the machine.
 *
 * @return Closure(string, string, array<string, mixed>): Symfony\Component\HttpClient\Response\MockResponse
 */

use Symfony\Component\HttpClient\Response\MockResponse;

$fixture = static function (string $factory, string $file, int $status = 200): MockResponse {
    return new MockResponse((string) file_get_contents(\dirname((string) (new ReflectionClass($factory))->getFileName()).'/Tests/Fixtures/'.$file), ['http_code' => $status]);
};

return static function (string $method, string $url, array $options) use ($fixture): MockResponse {
    $path = (string) parse_url($url, \PHP_URL_PATH);
    parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
    $afnor = 'Omnibill\\Afnor\\AfnorGatewayFactory';
    $cpro = 'Omnibill\\ChorusPro\\ChorusProGatewayFactory';
    $odoo = 'Omnibill\\Odoo\\OdooGatewayFactory';
    $pl = 'Omnibill\\Pennylane\\PennylaneGatewayFactory';
    $filter = json_decode(\is_string($query['filter'] ?? null) ? $query['filter'] : '[]', true);
    $number = \is_array($filter) && \is_array($filter[0] ?? null) && \is_string($filter[0]['value'] ?? null) ? $filter[0]['value'] : '';

    return match (true) {
        // Odoo (JSON-2): one route per model and method
        str_contains($path, '/json/2/') => match (substr($path, strpos($path, '/json/2/') + 8)) {
            'account.journal/search_read', 'account.journal/read' => $fixture($odoo, 'journal.json'),
            'account.account/search_read', 'account.account/read' => $fixture($odoo, 'accounts.json'),
            'res.partner/search_read' => $fixture($odoo, 'partners.json'),
            'account.move/create' => $fixture($odoo, 'created.json'),
            'account.move/action_post' => $fixture($odoo, 'posted.json'),
            'account.move/read' => $fixture($odoo, 'move-full.json'),
            'account.move.line/read' => $fixture($odoo, 'lines.json'),
            'res.country/search_read' => $fixture($odoo, 'country.json'),
            'res.partner/create' => $fixture($odoo, 'partner-created.json'),
            default => new MockResponse('{"name":"werkzeug.exceptions.NotFound","message":"not recorded"}', ['http_code' => 404]),
        },
        // Pennylane (Company API v2)
        str_contains($url, 'pennylane.com') && str_ends_with($path, '/journals') => $fixture($pl, 'journals.json'),
        str_contains($url, 'pennylane.com') && str_ends_with($path, '/ledger_accounts') => $fixture($pl, \in_array($number, ['615', '44566', '401EDF'], true) ? 'accounts-'.$number.'.json' : 'accounts-none.json'),
        str_contains($url, 'pennylane.com') && 'POST' === $method && str_ends_with($path, '/ledger_entries') => $fixture($pl, 'entry-created.json', 201),
        str_contains($url, 'pennylane.com') && str_contains($path, '/ledger_entries/') => $fixture($pl, 'entry.json'),
        str_contains($url, 'pennylane.com') && str_contains($path, '/suppliers') => $fixture($pl, 'supplier.json', 'POST' === $method ? 201 : 200),
        str_contains($url, 'piste.gouv.fr/api/oauth/token') => $fixture($cpro, 'token.json'),
        str_ends_with($path, '/cpro/factures/v1/deposer/flux') => $fixture($cpro, 'deposer-flux.json'),
        str_ends_with($path, '/cpro/factures/v1/rechercher/fournisseur') => $fixture($cpro, 'rechercher-fournisseur.json'),
        str_ends_with($path, '/token') => $fixture($afnor, 'token.json'),
        'POST' === $method && str_ends_with($path, '/v1/flows') => $fixture($afnor, 'flow-post-202.json', 202),
        str_ends_with($path, '/v1/flows/search') => $fixture($afnor, 'flow-search.json'),
        str_ends_with($path, '/v1/directory-line/search') => $fixture($afnor, 'directory-line-search.json'),
        str_ends_with($path, '/v1/flows/c1a2b3c4-d5e6-4f70-8a9b-0c1d2e3f4a5b') => $fixture($afnor, 'supplier-invoice.xml'),
        str_ends_with($path, '/v1/flows/d2b3c4d5-e6f7-4a80-9b0c-1d2e3f4a5b6c') => $fixture($afnor, 'cdar-212.xml'),
        str_contains($path, '/v1/flows/') && 'Metadata' === ($query['docType'] ?? null) => $fixture($afnor, 'flow-metadata-ok.json'),
        default => new MockResponse('{"errorCode":"NOT_FOUND"}', ['http_code' => 404]),
    };
};
