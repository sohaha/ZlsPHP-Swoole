<?php
/**
 * Swoole_websocket_frame Document
 *
 * @author seekwe <seekwe@gmail.com>
 */
class swoole_websocket_frame {

public $fd = 0;
public $data = '';
public $opcode = 1;
public $finish = true;

public function __toString() {}
static public function pack($data, $opcode = null, $finish = null, $mask = null) {}
static public function unpack($data) {}
}
