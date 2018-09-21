<?php

namespace Zls\Swoole;

/*
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @updatetime    2018-09-07 15:40:18
 */

use swoole_process;
use Z;
use Zls\Command\Utils;

class Main
{
    use Utils;
    public static    $client    = [];
    protected static $notify;
    protected static $reloading = false;
    protected static $pidFile;
    protected static $server;

    public function __construct()
    {
        self::$pidFile = z::config()->getStorageDirPath().'/swooleServer.pid';
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

    public function printLog($msg, $color = '')
    {
        $this->printStr('[ Swoole ]', 'blue', '');
        $this->printStr(': ');
        $this->printStrN(' '.$msg, $color);
    }

    protected function existProcess()
    {
        $pid = 0;
        if (file_exists(self::$pidFile)) {
            if ($pid = file_get_contents(self::$pidFile)) {
                $pid = swoole_process::kill($pid, 0) ? $pid : 0;
            } else {
                unlink(self::$pidFile);
            }
        }

        return $pid;
    }

    public function start()
    {
        /** @var \Zls_Config $zlsConfig */
        $zlsConfig = z::config();
        if ($zlsConfig->find('swoole')) {
            $config = z::config('swoole');
        } else {
            $config = include __DIR__.'/Config/swoole.php';
        }
        if (z::arrayGet($config, 'enable_coroutine')) {
            /** @noinspection PhpUndefinedClassInspection */
            /** @noinspection PhpUndefinedNamespaceInspection */
            \Swoole\Runtime::enableCoroutine();
        }
        if (!$this->existProcess()) {
            $lines = [
                '**********************************************************',
                '                    Information Panel                     ',
                '**********************************************************',
            ];
            $host = z::arrayGet($config, 'host');
            $port = z::arrayGet($config, 'port');
            $setProperties = z::arrayGet($config, 'set_properties', []);
            $enableHttp = z::arrayGet($config, 'enable_http', true);
            $enableWebSocker = z::arrayGet($config, 'enable_websocker', false);
            self::$notify = (false !== z::arrayGet($config, 'watch')) && extension_loaded('inotify');
            if (!$enableWebSocker && !$enableHttp) {
                $this->printLog('enable_http or enable_websocker must open one!', 'yellow');
                die;
            }
            $httpFn = function ($host, $port, $server) use ($zlsConfig, $config) {
                /** @noinspection PhpUndefinedMethodInspection */
                $zlsConfig->setZMethods('swooleBootstrap', function ($appdir) {
                    $this->bootstrap($appdir);
                });
                /** @var \Zls\Swoole\Http $http */
                $http = z::factory('Zls\Swoole\Http');
                $server->on('request', function ($request, $response) use ($zlsConfig, $config, $server, $http) {
                    $ignore = ['/robots.txt', '/favicon.ico'];
                    if (in_array(strtolower(z::arrayGet($request->server, 'path_info')), $ignore, true)) {
                        $response->end();
                    } else {
                        $content = $http->onRequest($request, $response, $zlsConfig, $config);
                        if ((bool)$content) {
                            $response->write($content);
                        }
                        $response->end();
                    }
                });
                $server->on('close', function ($server, $fd, $reactorId) use ($zlsConfig, $http) {
                    $http->onClose($server, $fd, $reactorId);
                });

                return '* Web    | Host : '.$host.', port: '.$port.', Enable  : 1';
            };
            if ($enableWebSocker) {
                $lines[] = '* Socket | IP   : '.$host.', port: '.$port.', OutTime : '.z::arrayGet($setProperties, 'heartbeat_check_interval', '-');
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
                /** @noinspection PhpUndefinedClassInspection */
                $server = new \swoole_http_server($host, $port);
                $lines[] = $httpFn($host, $port, $server);
            }
            $zlsConfig->setZMethods('swoole', function () use ($server) {
                return $server;
            });
            $server->on('Start', function () {
                //cli_set_process_title(z::config('swoole.pname'));
                self::$notify && $this->inotify();
            });
            $server->on('Shutdown', function () {
            });
            $defaultProperties = [
                //'pname' => 'swoole_zls',
                'document_root' => ZLS_PATH,
                'enable_static_handler' => true,
            ];
            $setProperties = $setProperties ? array_merge($defaultProperties, $setProperties) : $defaultProperties;
            $server->set(['pid_file' => self::$pidFile] + $setProperties);
            $lines[] = '**********************************************************';
            $line = implode("\n", $lines);
            echo $line.PHP_EOL;
            self::$server = $server;
            $server->start();
        } else {
            $this->printLog('Swoole service has started', 'red');
        }
    }

    private function bootstrap($appdir)
    {
        if (file_exists($bootstrap = $appdir.'bootstrap.php')) {
            /** @noinspection PhpIncludeInspection */
            include $bootstrap;
        }
    }

    protected function inotify()
    {
        $rootPath = z::realPath(ZLS_APP_PATH, true);
        $config = z::config();
        $ignoreFolder = [
            $config->getStorageDirPath(),
        ];
        $paths = z::scanFile($rootPath, 99, function ($v, $filename) use ($rootPath, $ignoreFolder) {
            $path = $rootPath.$filename;

            return !in_array(z::realPath($path, true), $ignoreFolder, true) && is_dir($rootPath.$filename);
        });
        $files = [];
        $this->forPath($files, $paths, $rootPath);
        /** @noinspection PhpComposerExtensionStubsInspection */
        $inotify = inotify_init();
        /** @noinspection PhpComposerExtensionStubsInspection */
        $mask = IN_MODIFY | IN_DELETE | IN_CREATE | IN_MOVE;
        foreach ($files as $file) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            inotify_add_watch($inotify, $file, $mask);
        }
        /** @noinspection PhpUndefinedFunctionInspection */
        swoole_event_add($inotify, function ($ifd) use ($inotify) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            $events = inotify_read($inotify);
            if (!$events) {
                return;
            }
            if ($pid = $this->existProcess()) {
                Z::command('kill -USR1 '.$pid, '', false, false);
            }
            /** @noinspection PhpUndefinedMethodInspection */
            /**self::$server && self::$server->reload(); */
        });
    }

    protected function forPath(&$lists, $paths, $d)
    {
        $folder = z::arrayGet($paths, 'folder', []);
        $path = [$d];
        foreach ($folder as $k => $v) {
            $_d = $d.$k.'/';
            $path[] = $_d;
            if (!$v['folder']) {
            } else {
                $this->forPath($lists, $v, $_d);
            }
        }
        $lists = array_merge($lists, $path);
    }
}
