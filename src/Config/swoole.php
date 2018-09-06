<?php
/*
server {
    listen 8082;
    root /data/wwwroot/;
    server_name local.swoole.com;

    location / {
        proxy_http_version 1.1;
        proxy_set_header Connection "keep-alive";
        proxy_set_header X-Real-IP $remote_addr;
        if (!-e $request_filename) {
             proxy_pass http://127.0.0.1:9501;
        }
    }
}
 */
return [
    'pname' => 'swoole_zls',
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
        // 守护模式
        'daemonize' => 1,
        //'max_connection' => 1024,//ulimit -n
        //'max_request'              => 50,
        //'task_worker_num'          => 4,
    ],
];
