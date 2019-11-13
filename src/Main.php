<?php
declare (strict_types=1);

namespace Zls\Swoole;

use Exception;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use swoole\Http\Response;
use Swoole\Http\Server as httpServer;
use swoole\Process as swooleProcess;
use Swoole\Coroutine as c;
use Swoole\Runtime;
use Swoole\Server;
use Swoole\Websocket\Server as websocketServer;
use Throwable;
use Z;
use Zls\Session\File as SessionFile;
use Zls\Swoole\Coroutine\Coroutine;
use Zls_Config;
use Zls\Swoole\Http;
use Zls\Swoole\Event;

class Main
{
    use Utils;
    public $masterPid = 0;
    public $serverOn = [];
    public $hotLoad = false;
    public $config;
    public $sessionFile;
    /** @var Server|httpServer|websocketServer $server */
    protected $server;
    protected $pidFile;
    protected $staticLocations;
    private $appName;

    public function __construct()
    {
        $appName       = explode('/', ZLS_PATH);
        $this->appName = Z::arrayGet($appName, count($appName) - 3, 'app');
        $this->pidFile = Z::realPathMkdir(Z::tempPath() . 'swoole', true, false, false) . 'swooleServer' . $this->appName . '-' . md5(ZLS_PATH) . '.pid';
        $this->initColor();
    }

    public function kill(): void
    {
        $this->printLog('Kill...', 'dark_gray');
        if ($pid = $this->existProcess()) {
            // if (trim(Z::command("kill -9 {$pid}", '', true, false))) {
            preg_match_all('/\d+/', Z::command("pstree -p {$pid}"), $pids);
            $pids = implode(' ', $pids[0]);
            Z::command("kill -9 {$pids}", '', true, false);
            // }
        } else {
            $this->printLog('Did not find the pid file, please manually view the process and end.', 'red');
        }
        $this->printLog('Done.', 'green');
    }

    /**
     * 进程是否已经启动
     * @return int
     */
    public function existProcess(): int
    {
        $pid = 0;
        if (file_exists($this->pidFile)) {
            if ($pid = (int)@file_get_contents($this->pidFile)) {
                /** @noinspection PhpVoidFunctionResultUsedInspection */
                $pid = swooleProcess::kill($pid, 0) ? $pid : 0;
            } else {
                @unlink($this->pidFile);
            }
        }

        return $pid;
    }

    public function stop($args): void
    {
        $this->printLog('Stoping...', 'dark_gray');
        if ($pid = $this->existProcess()) {
            swooleProcess::kill($pid); // Z::command("kill -15 {$pid}", '', true, false);
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
        /** @var Zls_Config $zlsConfig */
        $zlsConfig = Z::config();
        $this->swooleConfig($zlsConfig);
        if (!$this->existProcess()) {
            $daemonize = true;
            if (Z::arrayGet($args, ['--no-daemonize', 'N', 'n'], false)) {
                $daemonize = false;
            }
            $lines          = [];
            $this->serverOn = Z::arrayGet($this->config, 'on_event', []);
            $host           = Z::arrayGet($this->config, 'host');
            $runBefore      = Z::arrayGet($this->config, 'run_before');
            $port           = (int)Z::arrayGet($this->config, 'port');
            $setProperties  = Z::arrayGet($this->config, 'set_properties', []);
            if ($enableHttp = Z::arrayGet($this->config, 'enable_http', true)) {
                $this->setSession();
            }
            if (Z::arrayGet($this->config, 'enable_coroutine', true)) {
                Runtime::enableCoroutine();
                $this->setDbPool();
            }
            $enableWebSocker = Z::arrayGet($this->config, 'enable_websocker', false);
            $this->hotLoad   = (false !== Z::arrayGet($this->config, 'watch')) && extension_loaded('inotify');
            if (!$enableWebSocker && !$enableHttp) {
                $this->printLog('enable_http or enable_websocker must open one!', 'yellow');
                die;
            }
            try {
                // if ($enableWebSocker) {
                //     $ws      = 'ws://' . ($host === '0.0.0.0' ? '127.0.0.1' : $host) . ':' . $port;
                //     $lines[] = $this->printStr('[ Swoole Socker ]', 'blue', '') . ': ' . $ws . '  OutTime: ' . Z::arrayGet($setProperties, 'heartbeat_check_interval', '-');
                //     $server  = new WebsocketServer($host, $port);
                //     /** @var \Zls\Swoole\WebSocket $WebSocketClient */
                //     $WebSocketClient = Z::factory('Zls\Swoole\WebSocket');
                //     $server->on('open', [$WebSocketClient, 'open']);
                //     $server->on('message', [$WebSocketClient, 'message']);
                //     $server->on('task', [$WebSocketClient, 'task']);
                //     $server->on('finish', [$WebSocketClient, 'finish']);
                //     $server->on('close', [$WebSocketClient, 'close']);
                //     if ($enableHttp) {
                //         $lines[] = $this->webService($host, $port, $server, $zlsConfig);
                //     }
                // } else {
                $server  = new httpServer($host, $port);
                $lines[] = $this->webService($host, $port, $server, $zlsConfig);
                // }
                $this->server = $server;
                $zlsConfig->setZMethods('swoole', function () {
                    return $this->server;
                });
                $defaultProperties = [
                    'pname'                 => 'zls_swoole_' . $this->appName,
                    'document_root'         => Z::realPath(ZLS_PATH),
                    'enable_static_handler' => true,
                    'daemonize'             => $daemonize,
                ];
                $setProperties     = $setProperties ? array_merge($setProperties, $defaultProperties) : $defaultProperties;
                $this->setProcessTitle($defaultProperties['pname']);
                $this->staticLocations = ($setProperties['enable_static_handler']) ? Z::arrayGet($setProperties, 'static_handler_locations', []) : [];
                $server->set(['pid_file' => $this->pidFile] + $setProperties);
                if ($process = $this->getProcess()) {
                    foreach ($process as $p) {
                        $server->addProcess($p);
                    }
                }
                $this->BindingEvent();
                $line = implode("\n", $lines);
                echo $line . PHP_EOL;
                if (is_callable($runBefore)) {
                    $runBefore($server, $this->config);
                }
                if ((bool)Z::arrayGet($this->config, 'rpc_server.enable')) {
                    $rpcServerAddr = Z::arrayGet($this->config, 'rpc_server.addr', "0.0.0.0:8081");
                    $addr          = explode(':', $rpcServerAddr);
                    $this->printStrN('[ Swoole RPC ]: tcp://' . $rpcServerAddr, 'yellow', '');
                    $rpc = new RPC\Server($server->listen(Z::arrayGet($addr, 0), (int)Z::arrayGet($addr, 1), SWOOLE_SOCK_TCP), $this->config);
                    $zlsConfig->setZMethods('swooleRPC', function () use ($rpc) {
                        return $rpc;
                    });
                }
                if ((bool)Z::arrayGet($this->config, 'rpc_client.enable')) {
                    $clients = Z::arrayGet($this->config, 'rpc_client.client', []);
                    if ($clients) {
                        foreach ($clients as $cName => $client) {
                            $this->printLog('Start RPC client ' . $client['addr']);
                            RPC\Client::init($cName, $client);
                        }
                    }
                }
                $this->tick();
                $server->start();
            } catch (Throwable $e) {
                $this->printLog($e->getMessage(), 'red');
            }
        } else {
            $this->printLog('Swoole service has started', 'red');
        }
    }

    private function swooleConfig(Zls_Config $zlsConfig): void
    {
        if ($zlsConfig->find('swoole')) {
            $this->config = Z::config('swoole');
        } else {
            $this->config = include __DIR__ . '/Config/swoole.php';
        }
    }

    private function setProcessTitle($title): void
    {
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($title);
        } elseif (extension_loaded('proctitle') && function_exists('setproctitle')) {
            @setproctitle($title);
        }
    }

    private function setSession(): void
    {
        /** @var Zls_Config $zlsConfig */
        $zlsConfig     = Z::config();
        $sessionConfig = $zlsConfig->getSessionConfig();
        $sessionState  = Z::arrayGet($sessionConfig, 'autostart');
        $fileSession   = SessionFile::class;
        /** @var SessionFile $SessionHandle */
        if ($sessionState && !$SessionHandle = $zlsConfig->getSessionHandle()) {
            $SessionHandle = new $fileSession;
            $zlsConfig->setSessionHandle($SessionHandle);
        }
    }

    private function setDbPool(): void
    {
        // $this->log('startConnectionPool');
        // $pool = new Pool();
    }

    private function webService($host, $port, $server, Zls_Config $zlsConfig): string
    {
        /** @var Server $server */
        $zlsConfig->setZMethods('swooleBootstrap', function ($appdir) {
            $this->bootstrap($appdir);
        });
        /** @var Http $http */
        $http            = Z::factory(Http::class);
        $methodUriSubfix = $zlsConfig->getMethodUriSubfix();
        if (Z::arrayGet($zlsConfig->getSessionConfig(), 'autostart')) {
            Z::sessionStart();
        }
        $server->on('request', function ($request, $response) use ($zlsConfig, $server, $http, $methodUriSubfix) {
            /** @var Response $response */
            $ignore   = ['/robots.txt', '/favicon.ico'];
            $pathInfo = Z::arrayGet($request->server, 'path_info');
            $file     = Z::realPath(ZLS_PATH) . $pathInfo;
            if (!Z::strEndsWith($pathInfo, '.php') && !Z::strEndsWith($pathInfo, $methodUriSubfix) && $pathInfo !== '/' && is_file($file)) {
                $response->header('Content-Type', $this->fileMIME($file));
                $response->sendfile($file);

                return;
            }
            if (in_array(strtolower($pathInfo), $ignore, true)) {
                $response->end();

                return;
            }
            if ($this->LocaFile($pathInfo)) {
                $file = Z::realPath(ZLS_PATH) . $pathInfo;
                if (is_file($file)) {
                    $response->sendfile($file);
                } else {
                    $response->write('404 Not Found');
                    $response->status(404);
                    $response->end();
                }
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

    private function LocaFile($pathinfo): bool
    {
        if ((bool)$this->staticLocations && SWOOLE_VERSION < '4.4.0') {
            foreach ($this->staticLocations as $path) {
                if (Z::strBeginsWith($pathinfo, $path)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function on($method, callable $callable): void
    {
        if (array_key_exists($method, $this->serverOn)) {
            $callable = function (...$v) use ($callable, $method) {
                $callable(...$v);
                $this->serverOn[$method](...$v);
            };
        }
        $this->server->on($method, $callable);
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
     *
     * @param string $prefix
     * @url https://wiki.swoole.com/wiki/page/41.html
     */
    private function BindingEvent($prefix = 'on'): void
    {
        try {
            $EventClass   = new ReflectionClass(Event::class);
            $EventMethods = $EventClass->getMethods(ReflectionMethod::IS_PUBLIC);
            /** @var Event $EventInstance */
            $EventInstance = $EventClass->newInstance($this);
            Z::arrayMap($EventMethods, function ($v) use ($EventInstance, $prefix) {
                $name = Z::arrayGet((array)$v, 'name');
                if (Z::strBeginsWith($name, $prefix)) {
                    $method = substr($name, strlen($prefix));
                    $this->on($method, function (...$e) use ($EventInstance, $name) {
                        try {
                            return $EventInstance->$name(...$e);
                        } catch (Exception $e) {
                            echo $e->getMessage();

                            return false;
                        }
                    });
                }
            });
        } catch (ReflectionException $e) {
            echo $e->getMessage() . PHP_EOL;
            die;
        }
    }

    private function getProcess(): array
    {
        return [];
    }

    public function reload(): void
    {
        if ($pid = @file_get_contents($this->pidFile)) {
            if (extension_loaded('posix')) {
                /** @noinspection PhpComposerExtensionStubsInspection */
                posix_kill((int)$pid, SIGUSR1);
            } else {
                Z::command('kill -USR1 ' . $pid, '', false, false);
            }
        }
    }

    private function tick()
    {
        // 每分钟检查一次 pid 文件是否还存在
        \Swoole\Timer::tick(60000, function () {
            if ($this->masterPid && !file_exists($this->pidFile)) {
                @file_put_contents($this->pidFile, $this->masterPid);
            }
        });
    }
}
