<?php declare (strict_types = 1);

namespace Zls\Swoole;

use z;
use Zls;
use Zls\Command\Utils as CommandUtils;

/**
 * Zls
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-09-23 15:20
 */
trait Utils
{
    use CommandUtils;

    public function printLog($msg, $color = '')
    {
        $this->printStr('[ Swoole ]', 'blue', '');
        $this->printStr(': ');
        $this->printStrN($msg, $color);
    }

    public function log(...$_)
    {
        z::log($_, 'swoole');
    }

    public function errorLog(...$_)
    {
        z::log($_, 'swoole/err');
    }
}
