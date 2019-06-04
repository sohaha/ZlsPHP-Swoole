<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-31 12:59:44
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-06-04 17:18:55
 */

$swoole = Z::config('ini.swoole', true, []);

return [
    // 监听ip
    'host' => Z::arrayGet($swoole, 'host', '0.0.0.0'),
    // 监听端口
    'port' => Z::arrayGet($swoole, 'port', 8080),
    // 开启协程提高性能
    'enable_coroutine' => true,
    // 开启http服务器
    'enable_http' => Z::arrayGet($swoole, 'http', true),
    // 监听文件变化并热重启，生产环境建议关闭
    'watch' => Z::arrayGet($swoole, 'debug', false),
    'rpc_server' => [
        'enable' => false,
        'addr' => Z::arrayGet($swoole, 'rpc_server', "127.0.0.1:8081"),
        'method' => [
            // "App.Hi" => [\Business\Swoole\App::class, "Hi"],
        ],
    ],
    'rpc_client' => [
        'enable' => false,
        'client' => [
            // 'go' => [
            //     'addr' => z::config('ini.go.rpc_server'), // go rpc_server
            //     'timeout' => 1,
            // ],
        ],
    ],
    // 配置选项 https://wiki.swoole.com/wiki/page/274.html
    'set_properties' => [
        // 日志保存路径
        'log_file' => Z::config()->getStorageDirPath() . 'swoole/swoole.log',
        // 日志等级
        // 'log_level'             => 0,
        // 运行的 worker 进程数量
        'worker_num' => 10,
        'max_wait_time' => 10,
        // 'max_request'           => 3,
        // 'task_worker_num'       => 1,
        // 'task_enable_coroutine' => true,
        // 'max_connection' => 1024,// ulimit -n
    ],
    // 'run_before'       => function (\swoole\Server $server, $config) {
    //     \Business\Swoole\Tasks::init();
    // },
    // 要自定义注册事件回调函数,注意没有on,如果onWorkerStart则写WorkerStart
    // 具体参考swoole文档 https://wiki.swoole.com/wiki/page/41.html
    // 'on_event'         => [
    //     'WorkerStart' => function ($server, $worker_id) {
    //     },
    //     "Task"        => function (\swoole\Server $serv, Swoole\Server\Task $task) {
    //     },
    //     "Finish"      => function ($serv, $task_id, $data) {
    //     },
    // ],
];
