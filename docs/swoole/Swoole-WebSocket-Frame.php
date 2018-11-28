<?php
/**
 * Swoole\WebSocket\Frame Document
 * @author seekwe <seekwe@gmail.com>
 */

namespace Swoole\WebSocket;

class Frame
{

    public $fd = 0;
    public $data = '';
    public $opcode = 1;
    public $finish = true;

    public function __toString()
    {
    }

    static public function pack($data, $opcode = null, $finish = null, $mask = null)
    {
    }

    static public function unpack($data)
    {
    }
}
