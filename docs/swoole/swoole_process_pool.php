<?php
/**
 * Swoole_process_pool Document
 *
 * @author seekwe <seekwe@gmail.com>
 */
class swoole_process_pool {


public function __construct($worker_num, $ipc_type = null, $msgqueue_key = null) {}
public function __destruct() {}
public function on($event_name, $callback) {}
public function getProcess() {}
public function listen($host, $port = null, $backlog = null) {}
public function write($data) {}
public function start() {}
}
