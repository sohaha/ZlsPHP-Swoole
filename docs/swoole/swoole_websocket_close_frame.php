<?php
/**
 * Swoole_websocket_close_frame Document
 *
 * @author seekwe <seekwe@gmail.com>
 */
class swoole_websocket_close_frame extends \Swoole\WebSocket\Frame {

public $fd = 0;
public $data = '';
public $finish = true;
public $opcode = 8;
public $code = 1000;
public $reason = '';

public function __toString() {}
static public function pack($data, $opcode = null, $finish = null, $mask = null) {}
static public function unpack($data) {}
}
