<?php

namespace Omnibill\Harness;

use Omnibill\Registry;
use Omnibill\Request;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;

/**
 * The console: which gateways are installed and configured, what each
 * carries and does - on the settings in .env, nothing called. Sending is
 * bare's (bare --live).
 */
final class Console
{
    private const OPERATIONS = ['submit' => Request\Submit::class, 'fetch' => Request\FetchStatus::class, 'receive' => Request\Receive::class, 'set' => Request\SetStatus::class, 'lookup' => Request\Lookup::class, 'report' => Request\Report::class, 'notify' => Request\Notify::class];

    public static function create(): Application
    {
        $app = new Application('omnibill', '1.x');
        $command = new Command('gateways');
        $command->setDescription('Which gateways are installed and configured, what each carries and does');
        $command->setCode(static fn (InputInterface $in, OutputInterface $out): int => self::gateways($out));
        $app->addCommand($command);

        return $app;
    }

    private static function gateways(OutputInterface $out): int
    {
        $config = require __DIR__.'/../config/gateways.php';
        $factories = [];
        foreach (require __DIR__.'/../plugins.php' as [, $class]) {
            if (class_exists($class)) {
                $factory = str_ends_with($class, 'EmailGatewayFactory') ? new $class(new Mailer(Transport::fromDsn(getenv('MAILER_DSN') ?: 'null://null'))) : new $class(HttpClient::create());
                $factories[$factory->getName()] = $factory;
            }
        }
        $missing = static fn (array $g) => array_values(array_filter($g['needs'], static fn (string $k) => false === getenv($k) || '' === getenv($k)));
        $registry = new Registry($factories, array_map(static fn (array $g) => ['factory' => $g['factory'], 'options' => $missing($g) ? $g['example'] : array_filter($g['options'], static fn ($v) => null !== $v)], array_filter($config, static fn (array $g) => isset($factories[$g['factory']]))));

        $table = new Table($out);
        $table->setHeaders(['Gateway', 'Installed', 'Settings', 'Carries', 'Approved', 'Public sector', 'Does']);
        foreach ($config as $name => $g) {
            if (!isset($factories[$g['factory']])) {
                $table->addRow([$name, '<comment>no</comment>']);
                continue;
            }
            $gateway = $registry->get($name);
            $c = $gateway->capabilities();
            $table->addRow([$name, '<info>yes</info>', ($m = $missing($g)) ? '<comment>example (needs '.implode(', ', $m).')</comment>' : '<info>.env</info>', implode(', ', array_map(static fn ($s) => $s->value, $c->syntaxes)), $c->approved ? 'yes' : 'no', $c->publicSector ? 'yes' : 'no', implode(' ', array_keys(array_filter(self::OPERATIONS, static fn (string $r) => $gateway->supports($r))))]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
