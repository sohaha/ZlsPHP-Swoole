<?php
return [
    'host' => '0.0.0.0',
    'port' => 8080,
    'enable_websocker' => 0,
    // 配置选项 https://wiki.swoole.com/wiki/page/274.html
    'set_properties' => [
        'enable_static_handler' => true,
        'log_file' => ZLS_PATH.'/../swoole.log',
        //'heartbeat_idle_time'      => 600,
        //'heartbeat_check_interval' => 60,
        //'worker_num'               => 2,
        'daemonize' => 1, // 守护模式
        //'max_connection' => 1024,// ulimit -n
        //'max_request'              => 50,
        //'task_worker_num'          => 4,
    ],
];
