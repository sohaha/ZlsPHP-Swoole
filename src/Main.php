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
        self::$pidFile = z::config()->getStorageDirPath().'/swooleServer.pid1';
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
        /** @noinspection PhpUndefinedClassInspection */
        /** @noinspection PhpUndefinedNamespaceInspection */
        \Swoole\Runtime::enableCoroutine();
        if (!$this->existProcess()) {

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
            self::$notify = (false !== z::config('swoole.watch')) && extension_loaded('inotify');
            if (!$enableWebSocker && !$enableHttp) {
                $this->printLog('enable_http or enable_websocker must open one!', 'yellow');
                die;
            }
            $httpFn = function ($host, $port, $config, $server) {
                /** @noinspection PhpUndefinedMethodInspection */
                $config->setZMethods('swooleBootstrap', function ($applicationdir) {
                    $this->bootstrap($applicationdir);
                });
                /** @var \Zls\Swoole\Http $http */
                $http = z::factory('Zls\Swoole\Http');
                $server->on('request', function ($request, $response) use ($config, $server, $http) {
                    $ignore = ['/robots.txt', '/favicon.ico'];
                    if (in_array(strtolower(z::arrayGet($request->server, 'path_info')), $ignore, true)) {
                        $response->end();
                    } else {
                        $content = $http->onRequest($request, $response, $config);
                        if ((bool)$content) {
                            $response->write($content);
                        }
                        $response->end();
                    }
                });
                $server->on('close', function ($server, $fd, $reactorId) use ($config, $http) {
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
                    $lines[] = $httpFn($host, $port, $config, $server);
                }
            } else {
                /** @noinspection PhpUndefinedClassInspection */
                $server = new \swoole_http_server($host, $port);
                $lines[] = $httpFn($host, $port, $config, $server);
            }
            $config->setZMethods('swoole', function () use ($server) {
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
            echo $line."\n";
            self::$server = $server;
            $server->start();
        } else {
            $this->printLog('Swoole service has started', 'red');
        }
    }

    private function bootstrap($applicationdir)
    {
        if (file_exists($bootstrap = $applicationdir.'bootstrap.php')) {
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
            //self::$server && self::$server->reload();
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
