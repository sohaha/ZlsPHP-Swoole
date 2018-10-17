<?php
$swoole = z::config('ini.swoole', true, []);

return [
    'host'             => z::arrayGet($swoole, 'host', '0.0.0.0'),
    'port'             => z::arrayGet($swoole, 'port', 8080),
    // 开启协程
    'enable_coroutine' => false,
    // 开启连接池
    'enable_db_pool'   => false,
    // 开启http服务器
    'enable_http'      => z::arrayGet($swoole, 'http', true),
    // 监听文件变化并热重启，生产环境建议关闭
    'watch'            => z::arrayGet($swoole, 'debug', true),
    // 配置选项 https://wiki.swoole.com/wiki/page/274.html
    'set_properties'   => [
        // 守护模式
        'daemonize'             => z::arrayGet($swoole, 'daemonize', true),
        'log_file'              => Z::config()->getStorageDirPath() . 'swoole.log',
        'enable_static_handler' => true,
        'max_wait_time'         => 30,
        //'heartbeat_idle_time' => 300,
        //'heartbeat_check_interval' => 120,
        //'worker_num'               => 2,
        //'max_connection' => 1024,// ulimit -n
        //'max_request'              => 50,
        //'task_worker_num'          => 4,
    ],
];
