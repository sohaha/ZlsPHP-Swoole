<?php
/**
 * Swoole\WebSocket\CloseFrame Document
 *
 * @author seekwe <seekwe@gmail.com>
 */
namespace Swoole\WebSocket;
class CloseFrame extends \Swoole\WebSocket\Frame {

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
