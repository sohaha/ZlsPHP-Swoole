<?php
declare(strict_types=1);

namespace Zls\Swoole\Command;

use Z;
use Zls\Command\Command;
use Zls\Swoole\Main;
use Zls\Swoole\Main as swooleMain;

class Swoole extends Command
{
    public function execute($args)
    {
        try {
            $active = z::arrayGet($args, 2);
            if (class_exists('swoole_http_server')) {
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

    public function init($args)
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

    public function stop($args) :void
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->stop($args);
    }

    public function restart($args)
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->stop($args);
        $SwooleMain->start($args);
    }

    public function kill($args)
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->kill();
    }

    public function status($args)
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->status();
    }

    public function start($args)
    {
        /** @var swooleMain $SwooleMain */
        $SwooleMain = z::extension('Swoole\Main');
        $SwooleMain->start($args);
    }

    public function options()
    {
        return [];
    }

    public function example()
    {
    }

    public function reload()
    {
        $SwooleMain = new Main();
        $SwooleMain->reload();
    }

    public function description()
    {
        return 'Start Swolole';
    }

    public function commands()
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
