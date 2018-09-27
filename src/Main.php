<?php declare(strict_types=1);

namespace Zls\Swoole;

/*
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @updatetime    2018-09-07 15:40:18
 */

use ReflectionClass;
use ReflectionMethod;
use swoole_http_server;
use swoole_process;
use Z;

class Main
{
    use Utils;
    public $client  = [];
    public $hotLoad = false;
    public $config;
    public $sessionFile;
    /** @var \swoole_server|\swoole_http_server|\swoole_websocket_server $server */
    protected $server;
    protected $pidFile;

    public function __construct()
    {
        $this->pidFile = z::realPathMkdir(z::config()->getStorageDirPath(), true, false, false) . 'swooleServer.pid';
        $this->initColor();
    }

    public function stop()
    {
        $this->printLog('Stoping...', 'dark_gray');
        if ($pid = $this->existProcess()) {
            swoole_process::kill($pid);
            $time = 5;
            $status = true;
            while ($status) {
                --$time;
                $pid = $this->existProcess();
                if (!$pid) {
                    $status = false;
                } elseif (!$time) {
                    $status = false;
                    $this->printLog('stop failure, please try again!', 'red');
                }
                sleep(1);
            }
        }
        $this->printLog('Done.', 'green');
    }

    public function existProcess(): int
    {
        $pid = 0;
        if (file_exists($this->pidFile)) {
            if ($pid = (int)@file_get_contents($this->pidFile)) {
                /** @noinspection PhpVoidFunctionResultUsedInspection */
                $pid = swoole_process::kill($pid, 0) ? $pid : 0;
            } else {
                unlink($this->pidFile);
            }
        }

        return $pid;
    }

    /**
     * @throws \ReflectionException
     */
    public function start()
    {
        /** @var \Zls_Config $zlsConfig */
        $zlsConfig = z::config();
        if ($zlsConfig->find('swoole')) {
            $this->config = z::config('swoole');
        } else {
            $this->config = include __DIR__ . '/Config/swoole.php';
        }
        if (z::arrayGet($this->config, 'enable_coroutine')) {
            /** @noinspection PhpUndefinedClassInspection */
            /** @noinspection PhpUndefinedNamespaceInspection */
            \Swoole\Runtime::enableCoroutine();
        }
        if (!$this->existProcess()) {
            $lines = [];
            $host = z::arrayGet($this->config, 'host');
            $port = (int)z::arrayGet($this->config, 'port');
            $setProperties = z::arrayGet($this->config, 'set_properties', []);
            $enableHttp = z::arrayGet($this->config, 'enable_http', true);
            $enableWebSocker = z::arrayGet($this->config, 'enable_websocker', false);
            $this->hotLoad = (false !== z::arrayGet($this->config, 'watch')) && extension_loaded('inotify');
            if (!$enableWebSocker && !$enableHttp) {
                $this->printLog('enable_http or enable_websocker must open one!', 'yellow');
                die;
            }
            $httpFn = function ($host, $port, $server) use ($zlsConfig) {
                /** @var \swoole_server $server */
                $zlsConfig->setZMethods('swooleBootstrap', function ($appdir) {
                    $this->bootstrap($appdir);
                });
                /** @var \Zls\Swoole\Http $http */
                $http = z::factory('Zls\Swoole\Http');
                $server->on('request', function ($request, $response) use ($zlsConfig, $server, $http) {
                    /** @var \swoole_http_response $response */
                    $ignore = ['/robots.txt', '/favicon.ico'];
                    if (in_array(strtolower(z::arrayGet($request->server, 'path_info')), $ignore, true)) {
                        $response->end();
                    } else {
                        $content = $http->onRequest($request, $response, $zlsConfig, $this->config);
                        if ((bool)$content) {
                            $response->write($content);
                        }
                        $response->end();
                    }
                });
                $server->on('close', function ($server, $fd, $reactorId) use ($zlsConfig, $http) {
                    $http->onClose($server, $fd, $reactorId);
                });
                $url = 'http://' . ($host === '0.0.0.0' ? '127.0.0.1' : $host) . ':' . $port;

                return $this->printStr('[ Swoole Web ]', 'blue', '') . ': ' . $url;
            };
            if ($enableWebSocker) {
                $ws = 'ws://' . ($host === '0.0.0.0' ? '127.0.0.1' : $host) . ':' . $port;
                $lines[] = $this->printStr('[ Swoole Socker ]', 'blue', '') . ': ' . $ws . '  OutTime: ' . z::arrayGet($setProperties, 'heartbeat_check_interval', '-');
                /** @noinspection PhpUndefinedClassInspection */
                $server = new \swoole_websocket_server($host, $port);
                /** @var \Zls\Swoole\WebSocket $WebSocketClient */
                $WebSocketClient = z::factory('Zls\Swoole\WebSocket');
                $server->on('open', [$WebSocketClient, 'open']);
                $server->on('message', [$WebSocketClient, 'message']);
                $server->on('task', [$WebSocketClient, 'task']);
                $server->on('finish', [$WebSocketClient, 'finish']);
                $server->on('close', [$WebSocketClient, 'close']);
                if ($enableHttp) {
                    $lines[] = $httpFn($host, $port, $server);
                }
            } else {
                $server = new swoole_http_server($host, $port);
                $lines[] = $httpFn($host, $port, $server);
            }
            $zlsConfig->setZMethods('swoole', $server);
            $this->server = $server;
            $this->BindingEvent();
            $defaultProperties = [
                //'pname' => 'swoole_zls',
                'document_root' => ZLS_PATH,
                'enable_static_handler' => true,
            ];
            $setProperties = $setProperties ? array_merge($defaultProperties, $setProperties) : $defaultProperties;
            $server->set(['pid_file' => $this->pidFile] + $setProperties);
            if ($process = $this->getProcess()) {
                foreach ($process as $p) {
                    $server->addProcess($p);
                }
            }
            $line = implode("\n", $lines);
            echo $line . PHP_EOL;
            $server->start();
        } else {
            $this->printLog('Swoole service has started', 'red');
        }
    }

    private function bootstrap(string $appdir): void
    {
        if (file_exists($bootstrap = $appdir . 'bootstrap.php')) {
            /** @noinspection PhpIncludeInspection */
            include $bootstrap;
        }
    }

    /**
     * 绑定事件回调
     * @throws \ReflectionException
     * @url https://wiki.swoole.com/wiki/page/41.html
     */
    private function BindingEvent()
    {
        $EventClass = new ReflectionClass('Zls\Swoole\Event');
        $EventMethods = $EventClass->getMethods(ReflectionMethod::IS_PUBLIC);
        /** @var \Zls\Swoole\Event $EventInstance */
        $EventInstance = $EventClass->newInstance($this);
        Z::arrayMap($EventMethods, function ($v) use ($EventInstance) {
            $name = z::arrayGet((array)$v, 'name');
            if (Z::strBeginsWith($name, 'on')) {
                $this->server->on(substr($name, 2), [$EventInstance, $name]);
            }
        });
    }

    private function getProcess(): array
    {
        $processes = [];

        return $processes;
    }
}
