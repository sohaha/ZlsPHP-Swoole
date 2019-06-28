<?php
declare (strict_types=1);

namespace Zls\Swoole;

use Z;
use Zls\Session\File;

/*
 * 回调事件
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @updatetime    2018-11-28 19:21:01
 */

class Event
{
    use Utils;
    /** @var Main $main */
    private $main;
    private $zlsConfig;

    public function __construct(Main $main)
    {
        $this->main      = $main;
        $this->zlsConfig = z::config();
    }

    public function onWorkerStart($ws, $workerId)
    {
        if ($workerId === 0) {
            $this->inotify();
            $this->sessionGc();
        }
    }

    private function inotify()
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

    protected function forPath(array &$lists, array $paths, string $d)
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

    private function sessionGc()
    {
        $sessionConfig = $this->zlsConfig->getSessionConfig();
        $sessionState  = z::arrayGet($sessionConfig, 'autostart');
        /** @var File $SessionHandle */
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
        // $this->log('onWorkerStop');
    }

    public function onWorkerError(\swoole_server $serv, $worker_id, $worker_pid, $exit_code, $signal)
    {
        $err = [
            'id'     => $worker_id,
            'pid'    => $worker_pid,
            'code'   => $exit_code,
            'signal' => $signal,
        ];
        if ($exit_code !== 0) {
            $this->errorLog('onWorkerError', $err);
        }
        // \swoole_process::kill($worker_pid);
        // $serv->stop( $worker_id , true);
    }

    /**
     * 正常结束
     * @param \swoole_server $server
     */
    public function onShutdown($server)
    {
    }

    /**
     * 启动主进程的主线程回调
     * @param \swoole_server $server
     * @desc cli_set_process_title(z::config('swoole.pname'));
     */
    public function onStart($server)
    {
    }

    public function onWorkerExit()
    {

    }

    public function onConnect()
    {

    }

    public function onReceive()
    {

    }

    public function onTask()
    {

    }

    public function onFinish()
    {

    }

    public function onPipeMessage()
    {

    }

    public function onManagerStart()
    {

    }

    public function onManagerStop()
    {

    }

    public function onClose($server, $fd, $reactorId)
    {
        z::resetZls();
    }
}
