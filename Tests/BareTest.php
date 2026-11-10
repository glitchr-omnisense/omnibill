<?php

namespace Omnibill\Tests;

use Omnibill\Bridge\Symfony\OmnibillBundle;
use Omnibill\Registry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * Omnibill outside Symfony: the harness's bare script (docker/harness/bin/bare)
 * run in a PHP process of its own - this one has loaded the bundle's tests -
 * builds the registry by hand and sends an invoice through each gateway
 * installed, the platforms answering from their recorded answers and the
 * e-mail to an in-memory transport, then reports every class and file PHP
 * loaded on the way. None may be a framework's; of Symfony, only libraries:
 * the HTTP client, the mailer and its MIME.
 */
final class BareTest extends TestCase
{
    private const FRAMEWORK = '~^(?:Symfony\\\\Component\\\\(?:DependencyInjection|Config|HttpKernel|HttpFoundation|Form|Validator|Routing)|Symfony\\\\Bundle|Symfony\\\\Bridge|Doctrine(?!\\\\Common\\\\Lexer)|Twig)\\\\~';
    private const FRAMEWORK_FILES = '~/vendor/(?:symfony/(?:dependency-injection|config|http-kernel|http-foundation|form|validator|routing|[a-z-]*bundle|[a-z-]*bridge)|doctrine/(?!lexer/)[a-z-]+|twig)/~';

    /** @var array<string, mixed>|null */
    private static ?array $report = null;

    /** @return array<string, mixed> */
    private static function report(): array
    {
        if (null === self::$report) {
            [$status, self::$report] = self::php([__DIR__.'/../docker/harness/bin/bare', '--json']);
            self::assertSame(0, $status);
        }

        return self::$report;
    }

    public function testTheRegistryIsBuiltByHandAndNoClassOfAFrameworkIsLoaded(): void
    {
        $report = self::report();

        self::assertFalse($report['live']);
        self::assertContains(Registry::class, self::strings($report, 'symbols'), 'the registry was built there');
        self::assertSame([], self::framework(self::loaded($report)), 'no class nor file of a framework');
        $symfony = array_values(array_unique(array_map(static fn (string $s): string => implode('\\', \array_slice(explode('\\', $s), 0, 3)), self::matching('~^Symfony\\\\(?!Polyfill)~', self::strings($report, 'symbols')))));
        self::assertSame([], array_diff($symfony, ['Symfony\\Component\\HttpClient', 'Symfony\\Component\\Mailer', 'Symfony\\Component\\Mime', 'Symfony\\Contracts\\HttpClient', 'Symfony\\Contracts\\Service', 'Symfony\\Contracts\\EventDispatcher']), 'of Symfony, libraries alone');
        $plugins = require __DIR__.'/../docker/harness/plugins.php';
        self::assertIsArray($plugins);
        $installed = array_values(array_filter(array_column($plugins, 1), static fn (mixed $factory): bool => \is_string($factory) && class_exists($factory)));
        foreach ($installed as $factory) {
            self::assertContains($factory, self::strings($report, 'symbols'), 'every gateway package installed, its factory built');
        }
        $gateways = self::at($report, 'gateways');
        self::assertIsArray($gateways);
        self::assertCount(\count($installed), $gateways);
    }

    public function testAnInvoiceGoesThroughEachGateway(): void
    {
        $report = self::report();

        if (isset($report['email'])) {
            self::assertSame(['sent', 'compta@erable.example', 'Facture F-2026-042', ['F-2026-042.pdf', 'application/pdf'], ['sent', 'paid']], [self::at($report, 'email', 'status'), self::at($report, 'email', 'to'), self::at($report, 'email', 'subject'), self::at($report, 'email', 'attachment'), self::at($report, 'email', 'history')]);
        }
        if (isset($report['afnor'])) {
            self::assertSame(['accepted', 'deposited'], self::at($report, 'afnor', 'acknowledged'));
            self::assertSame(['FA-77.xml'], self::at($report, 'afnor', 'received_invoices'));
            self::assertSame([['F-2026-042', 'paid']], self::at($report, 'afnor', 'received_statuses'));
            $directory = self::at($report, 'afnor', 'directory');
            self::assertIsArray($directory);
            self::assertCount(2, $directory);
        }
        if (isset($report['chorus-pro'])) {
            self::assertSame(['accepted', 'made_available', 'MISE_A_DISPOSITION'], self::at($report, 'chorus-pro', 'status'));
        }
        self::assertNotEmpty(array_intersect(['email', 'afnor', 'chorus-pro'], array_keys($report)), 'at least one gateway package is installed beside this one');
    }

    public function testTheBooksGoThroughEachGatewayThatKeepsThem(): void
    {
        $report = self::report();
        $books = 0;
        foreach (['odoo' => ['42', 'ACH/2026/11/0001', 'posted'], 'pennylane' => ['610042', 'EDF-2026-11', 'draft']] as $name => $posted) {
            if (null === self::at($report, 'gateways', $name)) {
                continue;
            }
            ++$books;
            self::assertSame($posted, self::at($report, 'books', $name, 'posted'), $name);
            self::assertSame(['AC', 3, true, 12000], self::at($report, 'books', $name, 'fetched'), $name);
            self::assertNotNull(self::at($report, 'books', $name, 'supplier'), $name);
            self::assertSame(['journal-entries', 'contacts'], self::at($report, 'gateways', $name, 'contracts'), $name);
        }
        if (0 === $books) {
            self::markTestSkipped('Neither omnibill/odoo nor omnibill/pennylane is installed.');
        }
        self::assertNull(self::at($report, 'books', 'afnor'), 'an e-invoicing gateway keeps no books');
    }

    /**
     * A value deep in the report, null when a key is missing on the way.
     *
     * @param array<string, mixed> $report
     */
    private static function at(array $report, string ...$keys): mixed
    {
        $value = $report;
        foreach ($keys as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /** The check is not blind: the same report, once the bundle is loaded, names the framework. */
    public function testTheBundleDoesLoadTheFramework(): void
    {
        if (!class_exists(AbstractBundle::class)) {
            self::markTestSkipped('symfony/http-kernel is not installed.');
        }
        [$status, $report] = self::php(['-r', 'require getenv("OMNIBILL_AUTOLOAD"); class_exists($argv[1]) || exit(2); echo json_encode(["symbols" => [...get_declared_classes(), ...get_declared_interfaces(), ...get_declared_traits()], "files" => get_included_files()]);', '--', OmnibillBundle::class]);

        self::assertSame(0, $status);
        $framework = self::framework(self::loaded($report));
        self::assertContains(AbstractBundle::class, $framework);
        self::assertNotEmpty(preg_grep('~/symfony/http-kernel/~', $framework));
    }

    /**
     * @param array{symbols: list<string>, files: list<string>} $report
     *
     * @return list<string> the classes, interfaces, traits and files of a framework among those loaded
     */
    private static function framework(array $report): array
    {
        // Composer includes every installed package's "files" (installed for the
        // bridge's tests) before anything runs: loaded by the autoloader, not by Omnibill.
        $eager = require \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName()).'/autoload_files.php';
        $eager = \is_array($eager) ? array_values(array_filter($eager, 'is_string')) : [];
        $real = static fn (string $file): string => realpath($file) ?: $file;
        $files = array_diff(array_map($real, $report['files']), array_map($real, $eager));

        return [...self::matching(self::FRAMEWORK, $report['symbols']), ...self::matching(self::FRAMEWORK_FILES, $files)];
    }

    /**
     * @param array<string> $strings
     *
     * @return list<string> those $pattern matches
     */
    private static function matching(string $pattern, array $strings): array
    {
        return array_values(array_filter($strings, static fn (string $s): bool => 1 === preg_match($pattern, $s)));
    }

    /**
     * A list of strings in the report; empty when it is not there.
     *
     * @param array<string, mixed> $report
     *
     * @return list<string>
     */
    private static function strings(array $report, string $key): array
    {
        $value = $report[$key] ?? [];

        return \is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * What PHP loaded, as the report says it.
     *
     * @param array<string, mixed> $report
     *
     * @return array{symbols: list<string>, files: list<string>}
     */
    private static function loaded(array $report): array
    {
        return ['symbols' => self::strings($report, 'symbols'), 'files' => self::strings($report, 'files')];
    }

    /**
     * Runs PHP apart, on the autoloader of this run.
     *
     * @param list<string> $arguments
     *
     * @return array{int, array<string, mixed>} the exit status, the JSON printed
     */
    private static function php(array $arguments): array
    {
        $autoload = \dirname((string) (new \ReflectionClass(\Composer\Autoload\ClassLoader::class))->getFileName(), 2).'/autoload.php';
        $process = proc_open([\PHP_BINARY, ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['OMNIBILL_AUTOLOAD' => $autoload] + getenv());
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $report = json_decode($out, true);
        self::assertIsArray($report, 'PHP exited '.$status.': '.$err.$out);
        $object = [];
        foreach ($report as $key => $value) {
            $object[(string) $key] = $value;
        }

        return [$status, $object];
    }
}
