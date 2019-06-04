<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-05-31 16:01:00
 */

namespace Zls\Swoole;

use z;
use Zls;
use Zls\Command\Utils as CommandUtils;

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
