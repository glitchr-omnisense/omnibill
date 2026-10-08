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

    return match (true) {
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
