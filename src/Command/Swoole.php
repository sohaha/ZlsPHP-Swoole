<?php
declare(strict_types=1);

namespace Zls\Swoole\Command;

use Z;
use Zls\Command\Command;
use Zls\Swoole\Http as SwooleHttp;
use Zls\Swoole\Main;
use Zls\Swoole\Main as swooleMain;

class Swoole extends Command
{
    public function execute($args)
    {
        try {
            $active = z::arrayGet($args, 2, 'help');
            if (class_exists(SwooleHttp::class)) {
                if (method_exists($this, $active)) {
                    $this->$active($args);
                } else {
                    //$this->error('Warning: unknown method');
                    $this->help($args);
                }
            } else {
                $this->error("Warning: swoole not found !\nPlease install: https://wiki.swoole.com/wiki/page/6.html");
            }
        } catch (\Zls_Exception_Exit $e) {
            $this->printStrN($e->getMessage());
        }
    }

    public function init($args): void
    {
        $force      = Z::arrayGet($args, ['-force', 'F']);
        $file       = ZLS_APP_PATH . 'config/default/swoole.php';
        $originFile = Z::realPath(__DIR__ . '/../Config/swoole.php', false, false);
        $this->copyFile(
            $originFile,
            $file,
            $force,
            function ($status) use ($file) {
                if ($status) {
                    $this->success('config: ' . Z::safePath($file));
                    $this->printStr('Please modify according to the situation');
                } else {
                    $this->error('Profile already exists, or insufficient permissions');
                }
            },
            null
        );
    }

    public function stop($args): void
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->stop($args);
    }

    public function restart($args): void
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->stop($args);
        $SwooleMain->start($args);
    }

    public function kill($args): void
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->kill();
    }

    public function status($args): void
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->status();
    }

    public function start($args): void
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->start($args);
    }

    public function options(): array
    {
        return [];
    }

    public function example(): array
    {
        return [];
    }

    public function reload(): void
    {
        $SwooleMain = new Main();
        $SwooleMain->reload();
    }

    public function description(): string
    {
        return 'Start Swolole';
    }

    public function commands(): array
    {
        return [
            ' init'    => ['Publish Swoole configuration', ['--force, -F' => ' Overwrite old config file']],
            ' start'   => ['Start the swoole server', ['--no-daemonize, -n, -N' => 'Close Process Daemon']],
            ' stop'    => 'Stop the swoole server',
            ' reload'  => 'Hot overload file, does not support update configuration file',
            ' restart' => ['Restart the swolle service'],
        ];
    }
}
