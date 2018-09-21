<?php
$swoole = z::config('ini.swoole', true, []);

return [
    'host' => z::arrayGet($swoole, 'host', '0.0.0.0'),
    'port' => z::arrayGet($swoole, 'port', 8080),
    // 开启协程
    'enable_coroutine' => false,
    // 开启http服务器
    'enable_http' => z::arrayGet($swoole, 'http', true),
    'enable_websocker' => z::arrayGet($swoole, 'websocker', false),
    // 监听文件变化并热重启，生产环境建议关闭
    'watch' => z::arrayGet($swoole, 'debug', true),
    // 配置选项 https://wiki.swoole.com/wiki/page/274.html
    'set_properties' => [
        'enable_static_handler' => true,
        // 守护模式
        'daemonize' => z::arrayGet($swoole, 'daemonize', true),
        'log_file' => Z::config()->getStorageDirPath().'swoole.log',
        //'heartbeat_idle_time'      => 600,
        //'heartbeat_check_interval' => 60,
        //'worker_num'               => 2,
        //'max_connection' => 1024,// ulimit -n
        //'max_request'              => 50,
        //'task_worker_num'          => 4,
    ],
];
