<?php
declare (strict_types=1);
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
    /** @var PhpCoroutine|SwooleCoroutine */
    private static $co;

    public function __construct($timeout = 5, $sum = 1)
    {
        self::$co = Z::isSwoole() ? new SwooleCoroutine($timeout, $sum) : new PhpCoroutine($timeout, $sum);
    }

    public function getInstance()
    {
        return self::$co;
    }

    public static function instance($timeout = 5, $sum = 1)
    {
        return (new self($timeout, $sum))->getInstance();
    }

    public static function sleep($time)
    {
        self::$co->sleep($time);
    }

    public static function go(\Closure $func)
    {
        self::$co->go($func);
    }

    public static function sync(\Closure $func)
    {
        return self::$co->sync($func);
    }

    public static function wait(Coroutine $task)
    {
        return self::$co->wait($task);
    }
}
