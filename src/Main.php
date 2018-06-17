<?php

namespace Zls\Swoole;

/**
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2017-08-28 18:07
 */
use Z;

class Main
{
    public static $client = [];

    public function reload()
    {
        echo "Reloading...\t\n";
        $cmd = 'cmd=$(pidof ' . Z::config('swoole.pname') . ') && kill   -USR1 "$cmd"';
        return z::command($cmd, null, true, false);
    }


    public function restart()
    {
        $this->stop();
        \sleep(1);
        $this->start();
    }


    public function stop()
    {
        echo 'Stop...' . \PHP_EOL;
        $cmd = 'cmd=$(pidof ' . z::config('swoole.pname') . ') && kill "$cmd"';
        echo z::command($cmd, null, true, false);
    }


    public function start()
    {
        $lines = [
            '**********************************************************',
            '                    Information Panel                     ',
            '**********************************************************',
        ];
        /** @var \Zls_Config $config */
        $config = z::config();
        $host = z::config('swoole.host');
        $port = z::config('swoole.port');
        $setProperties = z::config('swoole.set_properties');
        $enableHttp = z::config('swoole.enable_http') ?: 1;
        $enableWebSocker = z::config('swoole.enable_websocker') ?: 0;
        if (!$enableWebSocker && !$enableHttp) {
            echo 'warning: enable_http or enable_websocker must open one !';
            die;
        }
        $httpFn = function ($host, $port, $config, $server) {
            $config->setZMethods('swooleBootstrap', function ($applicationdir) {
                $this->bootstrap($applicationdir);
            });
            /** @var \Zls\Swoole\Http $http */
            $http = z::factory('Zls\Swoole\Http');
            $server->on('request', function ($request, $response) use ($config, $server, $http) {
                if (strtolower(z::arrayGet($request->server, 'path_info')) === '/favicon.ico') {
                    $response->end();
                } else {
                    $content = $http->onRequest($request, $response, $config);
                    if (!!$content) {
                        $response->write($content);
                    }

                    $response->end();
                }
            });
            $server->on('close', function ($server, $fd, $reactorId) use ($config, $http) {
                $http->onClose($server, $fd, $reactorId);
            });

            return '* Web    | Host : ' . $host . ', port: ' . $port . ', Enable  : 1';
        };
        if ($enableWebSocker) {
            $lines[] = '* Socket | IP   : ' . $host . ', port: ' . $port . ', OutTime : ' . z::arrayGet($setProperties, 'heartbeat_check_interval', '-');
            $server = new \swoole_websocket_server($host, $port);
            /** @var \Zls\Swoole\WebSocket $WebSocketClient */
            $WebSocketClient = z::factory('Zls\Swoole\WebSocket');

            $server->on('open', [$WebSocketClient,'open']);
            $server->on('message', [$WebSocketClient, 'message']);
            $server->on('task', [$WebSocketClient, 'task']);
            $server->on('finish', [$WebSocketClient, 'finish']);
            $server->on('close', [$WebSocketClient, 'close']);
            if ($enableHttp) {
                $lines[] = $httpFn($host, $port, $config, $server);
            }
        } else {
            $server = new \swoole_http_server($host, $port);
            $lines[] = $httpFn($host, $port, $config, $server);
        }
        $config->setZMethods('swoole', function () use ($server) {
            return $server;
        });
        $server->on('Start', function () {
            cli_set_process_title(z::config('swoole.pname'));
        });
        if (is_array($setProperties)) {
            $server->set($setProperties);
        }
        $lines[] = '**********************************************************';
        $line = implode("\n", $lines);
        echo $line . "\n";
        $server->start();
    }

    private function bootstrap($applicationdir)
    {
        if (file_exists($bootstrap = $applicationdir . 'bootstrap.php')) {
            include($bootstrap);
        }
    }
}
