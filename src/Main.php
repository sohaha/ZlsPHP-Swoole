<?php declare (strict_types = 1);

namespace Zls\Swoole;

/*
 * Swoole
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @updatetime    2018-09-07 15:40:18
 */

use ReflectionMethod;
use swoole_http_server;
use swoole_process;
use swoole_websocket_server;
use Z;

class Main
{
    use Utils;
    public $client = [];
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

    public function kill(): void
    {
        $this->printLog('Kill...', 'dark_gray');
        if ($pid = $this->existProcess()) {
            preg_match_all('/\d+/', Z::command("pstree -p {$pid}"), $pids);
            $pids = join(' ', $pids[0]);
            Z::command("kill -9 {$pids}", '', true, false);
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
                @unlink($this->pidFile);
            }
        }

        return $pid;
    }

    public function stop(): void
    {
        $this->printLog('Stoping...', 'dark_gray');
        if ($pid = $this->existProcess()) {
            swoole_process::kill($pid);
            $time   = time() + 15;
            $status = true;
            while ($status) {
                $pid = $this->existProcess();
                if (!$pid) {
                    $status = false;
                } elseif (time() >= $time) {
                    $status = false;
                    $this->printLog('pid: ' . $pid . ', stop failure, please try again!', 'red');
                }
            }
        }
        $this->printLog('Done.', 'green');
    }

    public function status(): void
    {
        if ($pid = $this->existProcess()) {
            $this->printLog("swoole is running, PID : {$pid}.");
        } else {
            $this->printLog('swoole is not running');
        }
    }

    public function start($args): void
    {
        /** @var \Zls_Config $zlsConfig */
        $zlsConfig = z::config();
        $this->config($zlsConfig);
        if (!$this->existProcess()) {
            $lines         = [];
            $host          = z::arrayGet($this->config, 'host');
            $port          = (int)z::arrayGet($this->config, 'port');
            $daemonize     = Z::arrayGet($args, ['--no-daemonize', 'N', 'n'], true);
            $setProperties = z::arrayGet($this->config, 'set_properties', []);
            if ($enableHttp = z::arrayGet($this->config, 'enable_http', true)) {
                $this->setSession();
            }
            if (z::arrayGet($this->config, 'enable_coroutine')) {
                \Swoole\Runtime::enableCoroutine();
                $this->setDbPool();
            }
            $enableWebSocker = z::arrayGet($this->config, 'enable_websocker', false);
            $this->hotLoad   = (false !== z::arrayGet($this->config, 'watch')) && extension_loaded('inotify');
            if (!$enableWebSocker && !$enableHttp) {
                $this->printLog('enable_http or enable_websocker must open one!', 'yellow');
                die;
            }
            if ($enableWebSocker) {
                $ws      = 'ws://' . ($host === '0.0.0.0' ? '127.0.0.1' : $host) . ':' . $port;
                $lines[] = $this->printStr('[ Swoole Socker ]', 'blue', '') . ': ' . $ws . '  OutTime: ' . z::arrayGet($setProperties, 'heartbeat_check_interval', '-');
                $server  = new swoole_websocket_server($host, $port);
                /** @var \Zls\Swoole\WebSocket $WebSocketClient */
                $WebSocketClient = z::factory('Zls\Swoole\WebSocket');
                $server->on('open', [$WebSocketClient, 'open']);
                $server->on('message', [$WebSocketClient, 'message']);
                $server->on('task', [$WebSocketClient, 'task']);
                $server->on('finish', [$WebSocketClient, 'finish']);
                $server->on('close', [$WebSocketClient, 'close']);
                if ($enableHttp) {
                    $lines[] = $this->webService($host, $port, $server, $zlsConfig);
                }
            } else {
                $server  = new swoole_http_server($host, $port);
                $lines[] = $this->webService($host, $port, $server, $zlsConfig);
            }
            $this->server = $server;
            $zlsConfig->setZMethods('swoole', function () {
                return $this->server;
            });
            $this->BindingEvent();
            $defaultProperties = [
                //'pname' => 'swoole_zls',
                'document_root'         => ZLS_PATH,
                'enable_static_handler' => true,
                'daemonize'             => $daemonize,
            ];
            $setProperties     = $setProperties ? array_merge($setProperties, $defaultProperties) : $defaultProperties;
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

    private function config(\Zls_Config $zlsConfig): void
    {
        if ($zlsConfig->find('swoole')) {
            $this->config = z::config('swoole');
        } else {
            $this->config = include __DIR__ . '/Config/swoole.php';
        }
    }

    private function setSession(): void
    {
        /** @var \Zls_Config $zlsConfig */
        $zlsConfig     = z::config();
        $sessionConfig = $zlsConfig->getSessionConfig();
        $sessionState  = z::arrayGet($sessionConfig, 'autostart');
        $fileSession   = 'Zls\Session\File';
        /** @var \Zls\Session\File $SessionHandle */
        if ($sessionState && !$SessionHandle = $zlsConfig->getSessionHandle()) {
            $SessionHandle = new $fileSession;
            $zlsConfig->setSessionHandle($SessionHandle);
        }
    }

    private function setDbPool(): void
    {
        $this->log('启动连接池');
        $pool = new Pool();
    }

    public function log(...$_)
    {
        z::log($_, 'swoole');
    }

    private function webService($host, $port, $server, \Zls_Config $zlsConfig): string
    {
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
                $content = $http->onRequest($request, $response, $server, $zlsConfig, $this->config);
                if ((bool)$content) {
                    $response->write($content);
                }
                $response->end();
            }
        });
        $url = 'http://' . ($host === '0.0.0.0' ? '127.0.0.1' : $host) . ':' . $port;

        return $this->printStr('[ Swoole Web ]', 'blue', '') . ': ' . $url;
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
     * @param string $prefix
     * @url https://wiki.swoole.com/wiki/page/41.html
     */
    private function BindingEvent($prefix = 'on'): void
    {
        try {
            $EventClass   = new \ReflectionClass('Zls\Swoole\Event');
            $EventMethods = $EventClass->getMethods(ReflectionMethod::IS_PUBLIC);
            /** @var \Zls\Swoole\Event $EventInstance */
            $EventInstance = $EventClass->newInstance($this);
            Z::arrayMap($EventMethods, function ($v) use ($EventInstance, $prefix) {
                $name = z::arrayGet((array)$v, 'name');
                if (Z::strBeginsWith($name, $prefix)) {
                    $this->server->on(substr($name, strlen($prefix)), function (...$e) use ($EventInstance, $name) {
                        try {
                            return $EventInstance->$name(...$e);
                        } catch (\Exception $e) {
                            echo $e->getMessage();

                            return false;
                        }
                    });
                }
            });
        } catch (\ReflectionException $e) {
            echo $e->getMessage() . PHP_EOL;
            die;
        }
    }

    private function getProcess(): array
    {
        $processes = [];

        return $processes;
    }

    public function reload()
    {
        $pid = @file_get_contents($this->pidFile);
        if (extension_loaded('posix')) {
            posix_kill((int)$pid, SIGUSR1);
        } else {
            Z::command('kill -USR1 ' . $pid, '', false, false);
        }
    }
}
