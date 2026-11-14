<?php

namespace App\Command\Server;

use App\Shared\Server\MercureHubManager;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Process\Console\Attribute\AsCommand;
use NeoPHP\Process\Console\Contract\AbstractConsole;
use NeoPHP\Process\Console\Contract\InputInterface;
use NeoPHP\Process\Console\Contract\OutputInterface;

#[AsCommand(name: 'battlegame:server:start', description: 'Starts BattleGame on APP_URL with the Mercure hub')]
class ServerStartCommand extends AbstractConsole
{
    private const PHP_HOST = '127.0.0.1';
    private const PHP_PORT = 8001;
    private const ROUTER = 'vendor/neophp/framework/src/process/Console/Resources/router.php';

    public function __construct(
        protected MercureHubManager $hub,
        #[Autowire(param: 'kernel.root_path')]
        protected string $rootPath,
        #[Autowire(config: 'packages.mercure.jwt.secret')]
        protected ?string $secret,
        #[Autowire(config: 'framework.app.url')]
        protected string $appUrl,
    ) {
    }

    protected function do(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->hub->isInstalled()) {
            $output->error('The Mercure hub is not installed. Run "php bin/neo battlegame:server:install" first.');

            return self::FAILURE;
        }

        if (!$this->secret) {
            $output->error('The Mercure JWT secret is empty (packages.mercure.jwt.secret, MERCURE_JWT_SECRET).');

            return self::FAILURE;
        }

        $url = rtrim($this->appUrl, '/');

        foreach ([parse_url($url, PHP_URL_PORT) ?? 443, self::PHP_PORT] as $port) {
            if ($this->isPortUsed($port)) {
                $output->error(sprintf('Port %d is already in use: BattleGame (or another server) is still running. Stop it first.', $port));

                return self::FAILURE;
            }
        }

        $env = array_merge(getenv(), [
            'MERCURE_JWT_SECRET' => $this->secret,
            'APP_URL' => $url,
        ]);
        $io = [STDIN, STDOUT, STDERR];

        $php = proc_open([PHP_BINARY, '-S', self::PHP_HOST . ':' . self::PHP_PORT, '-t', 'public', self::ROUTER], $io, $pipes, $this->rootPath, $env);
        $hub = proc_open([$this->hub->getBinary(), 'run', '--config', $this->hub->getConfig(), '--adapter', 'caddyfile'], $io, $pipes, $this->rootPath, $env);
        $log = $this->rootPath . '/var/log/scheduler.log';
        $scheduler = proc_open([PHP_BINARY, 'bin/neo', 'schedule:work'], [STDIN, ['file', $log, 'a'], ['file', $log, 'a']], $pipes, $this->rootPath, $env);

        $output->success(sprintf(
            'BattleGame is running on %s (admin: %s), scheduler log in var/log/scheduler.log. Press Ctrl+C to stop.',
            $url,
            $url . '/admin',
        ));

        while (proc_get_status($php)['running'] && proc_get_status($hub)['running']) {
            usleep(500000);
        }

        proc_terminate($php);
        proc_terminate($hub);
        proc_terminate($scheduler);

        return self::SUCCESS;
    }

    protected function isPortUsed(int $port): bool
    {
        $socket = @fsockopen(self::PHP_HOST, $port, $code, $message, 0.2);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}