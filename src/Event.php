<?php declare (strict_types=1);

namespace Zls\Swoole;

use Z;

/*
 * 回调事件
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @updatetime    2018-11-28 19:21:01
 */

class Event
{
    /** @var Main $main */
    private $main;
    private $zlsConfig;

    public function __construct(Main $main)
    {
        $this->main      = $main;
        $this->zlsConfig = z::config();
    }

    public function onWorkerStart($ws, $workerId): void
    {
        if ($workerId === 0) {
            $this->inotify();
            //$this->sessionGc();
        }
    }

    private function inotify(): void
    {
        if ($this->main->hotLoad) {
            try {
                $rootPath     = z::realPath(ZLS_APP_PATH, true);
                $config       = z::config();
                $ignoreFolder = [
                    $config->getStorageDirPath(),
                ];
                $paths        = z::scanFile($rootPath, 99, function ($v, $filename) use ($rootPath, $ignoreFolder) {
                    $path = $rootPath . $filename;

                    return !in_array(z::realPath($path, true), $ignoreFolder, true) && is_dir($rootPath . $filename);
                });
                $files        = [];
                $this->forPath($files, $paths, $rootPath);
                /** @noinspection PhpComposerExtensionStubsInspection */
                $inotify = inotify_init();
                /** @noinspection PhpComposerExtensionStubsInspection */
                $mask = 2 | 512 | 256 | 192;
                foreach ($files as $file) {
                    /** @noinspection PhpComposerExtensionStubsInspection */
                    inotify_add_watch($inotify, $file, $mask);
                }
                swoole_event_add($inotify, function ($ifd) use ($inotify) {
                    /** @noinspection PhpComposerExtensionStubsInspection */
                    $events = inotify_read($inotify);
                    if (!$events) {
                        return;
                    }
                    if ($pid = $this->main->existProcess()) {
                        $this->main->reload();
                        echo '[' . date('y-m-d H:i:s') . '] reload' . PHP_EOL;
                    }
                });
            } catch (\Exception $e) {
                $errCode = swoole_last_error();
                $errMsg  = $e->getMessage() . ' [' . swoole_strerror($errCode) . ']';
                echo '[' . date('y-m-d H:i:s') . '] ' . $errMsg . PHP_EOL;
            }
        }
    }

    protected function forPath(array &$lists, array $paths, string $d): void
    {
        $folder = z::arrayGet($paths, 'folder', []);
        $path   = [$d];
        foreach ($folder as $k => $v) {
            $_d     = $d . $k . '/';
            $path[] = $_d;
            if (!$v['folder']) {
            } else {
                $this->forPath($lists, $v, $_d);
            }
        }
        $lists = array_merge($lists, $path);
    }

    private function sessionGc(): void
    {
        $sessionConfig = $this->zlsConfig->getSessionConfig();
        $sessionState  = z::arrayGet($sessionConfig, 'autostart');
        /** @var \Zls\Session\File $SessionHandle */
        if ($sessionState && $SessionHandle = $this->zlsConfig->getSessionHandle()) {
            $fileSession   = 'Zls\Session\File';
            $isFileSession = get_class($SessionHandle) === $fileSession;
            if ($sessionState && $isFileSession) {
                $lifetime = z::arrayGet($sessionConfig, 'lifetime', 600);
                swoole_timer_tick(3000, function () use ($SessionHandle, $lifetime) {
                    $SessionHandle->swooleGc($lifetime);
                });
            }
        }
    }

    public function onWorkerStop()
    {
        z::log('onWorkerStop', 's');
    }

    public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code, $signal)
    {
        $err = [
            '是异常进程的编号'          => $worker_id,
            '是异常进程的ID'          => $worker_pid,
            '退出的状态码，范围是 1 ～255' => $exit_code,
            '进程退出的信号'           => $signal,
        ];
        z::log(['onWorkerError', $err], 's');
        // \swoole_process::kill($worker_pid);
        // $serv->stop( $worker_id , true);
    }

    /**
     * 正常结束
     * @param \swoole_server $server
     */
    public function onShutdown($server): void
    {
        z::log('onShutdown', 's');
    }

    /**
     * 启动主进程的主线程回调
     * @param \swoole_server $server
     * @desc cli_set_process_title(z::config('swoole.pname'));
     */
    public function onStart($server): void
    {
    }

    public function onClose($server, $fd, $reactorId): void
    {
        z::resetZls();
    }
}
