<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-05-31 16:04:55
 */

namespace Zls\Swoole;

use Z;
use Zls\Swoole\Coroutine\SwooleCoroutine;

class Co
{
    public static function instance($timeout = 5, $sum = 1)
    {
        if (z::isSwoole()) {
            return new Coroutine\SwooleCoroutine($timeout, $sum);
        } else {
            return new Coroutine\PhpCoroutine($timeout, $sum);
        }
    }
}
