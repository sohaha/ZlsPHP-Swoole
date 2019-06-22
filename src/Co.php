<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-05-31 16:04:55
 */

namespace Zls\Swoole;

use Z;
use Zls\Swoole\Coroutine\Coroutine;
use Zls\Swoole\Coroutine\SwooleCoroutine;
use Zls\Swoole\Coroutine\PhpCoroutine;

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

    public static function sleep($time)
    {
        if (z::isSwoole()) {
            SwooleCoroutine::sleep($time);
        } else {
            PhpCoroutine::sleep($time);
        }
    }

    public static function go(\Closure $func)
    {
        if (z::isSwoole()) {
            SwooleCoroutine::go($func);
        } else {
            PhpCoroutine::go($func);
        }
    }

    public static function sync(\Closure $func)
    {
        return (z::isSwoole()) ? SwooleCoroutine::sync($func) : PhpCoroutine::sync($func);
    }

    public static function wait(Coroutine $task)
    {
        return (z::isSwoole()) ? SwooleCoroutine::wait($task) : PhpCoroutine::wait($task);
    }
}
