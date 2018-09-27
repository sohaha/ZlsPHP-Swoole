<?php declare(strict_types=1);

namespace Zls\Swoole;

use Z;

/*
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2017-08-28 18:07
 */

class Event
{
    /** @var Main $main */
    private $main;
    private $zlsConfig;

    public function __construct(Main $main)
    {
        $this->main = $main;
        $this->zlsConfig = z::config();
    }

    public function onWorkerStart($ws, $workerId): void
    {
        //z::log([$this->zlsConfig->getSessionConfig(), z::arrayGet($this->zlsConfig->getSessionConfig(), 'autostart'), $workerId, time()], 'swoole');
        if ($workerId === 0) {
            $sessionConfig = $this->zlsConfig->getSessionConfig();
            // 如果开启了session，并且是文件托管，要回收
            $sessionState = z::arrayGet($sessionConfig, 'autostart');
            /** @var \Zls\Session\File $SessionHandle */
            $SessionHandle = $this->zlsConfig->getSessionHandle();
            $isFileSession = get_class($SessionHandle) === 'Zls\Session\File';
            if ($sessionState && $isFileSession) {
                swoole_timer_tick(1000, function () use ($SessionHandle, $sessionConfig) {
                    $SessionHandle->swooleGc(z::arrayGet($sessionConfig, 'lifetime', 600));
                });
            }
        }
    }

    /**
     * 正常结束
     * @param \swoole_server $server
     */
    public function onShutdown($server): void
    {

    }

    /**
     * 启动主进程的主线程回调
     * @param \swoole_server $server
     */
    public function onStart($server): void
    {
        //cli_set_process_title(z::config('swoole.pname'));
        if ($this->main->hotLoad) {
            $this->inotify();
        }
    }


    private function inotify(): void
    {
        $rootPath = z::realPath(ZLS_APP_PATH, true);
        $config = z::config();
        $ignoreFolder = [
            $config->getStorageDirPath(),
        ];
        $paths = z::scanFile($rootPath, 99, function ($v, $filename) use ($rootPath, $ignoreFolder) {
            $path = $rootPath . $filename;

            return !in_array(z::realPath($path, true), $ignoreFolder, true) && is_dir($rootPath . $filename);
        });
        $files = [];
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
                Z::command('kill -USR1 ' . $pid, '', false, false);
            }
        });
    }

    protected function forPath(array &$lists, array $paths, string $d): void
    {
        $folder = z::arrayGet($paths, 'folder', []);
        $path = [$d];
        foreach ($folder as $k => $v) {
            $_d = $d . $k . '/';
            $path[] = $_d;
            if (!$v['folder']) {
            } else {
                $this->forPath($lists, $v, $_d);
            }
        }
        $lists = array_merge($lists, $path);
    }
}
