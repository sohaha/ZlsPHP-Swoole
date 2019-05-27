<?php
$swoole = z::config('ini.swoole', true, []);

return [
    // 监听 ip
    'host'             => z::arrayGet($swoole, 'host', '0.0.0.0'),
    // 监听端口
    'port'             => z::arrayGet($swoole, 'port', 8081),
    // 开启协程提高性能
    'enable_coroutine' => true,
    // 开启http服务器
    'enable_http'      => z::arrayGet($swoole, 'http', true),
    // 监听文件变化并热重启，生产环境建议关闭
    'watch'            => z::arrayGet($swoole, 'debug', false),
    // 配置选项 https://wiki.swoole.com/wiki/page/274.html
    'set_properties'   => [
        // 日志保存路径
        'log_file'   => Z::config()->getStorageDirPath() . 'swoole/swoole.log',
        // 日志等级
        'log_level'  => 0,
        // 运行的 worker 进程数量
        'worker_num' => 4,
        //'max_connection' => 1024,// ulimit -n
    ],
    'run_before'       => function (\swoole\Server $server, $config) {
    },
    // 要自定义注册事件回调函数,注意没有on,如果onWorkerStart则写WorkerStart
    // 具体参考swoole文档 https://wiki.swoole.com/wiki/page/41.html
    'on_event'         => [
        // 'WorkerStart' => function ($server, $worker_id) {
        // },
    ],
];
