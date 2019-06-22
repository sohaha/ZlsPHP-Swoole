<?php declare (strict_types = 1);
/*
 * @Author: seekwe
 * @Date:   2019-05-28 15:27:25
 * @Last Modified by:   seekwe
 * @Last Modified time: 2019-05-31 16:05:11
 */

namespace Zls\Swoole\Coroutine;

use Z;
use Zls\Swoole\Utils;

abstract class Coroutine
{
    use Utils;
    abstract  public function __construct(
        $timeout,
        $sum
    );
    abstract public function run(string $name, \Closure $func);
    abstract public function data();
    abstract public static function defer(\Closure $func);
    abstract public static function go(\Closure $func);

    public static function sleep($time)
    {
        sleep($time);
    }

    public static function wait(Coroutine $task)
    {
        return z::arrayGet($task->data(), 'task.data');
    }

    public static function sync(\Closure $func)
    {
        $task = new static(-1, 1);
        $task->run("task", $func);
        return $task;
    }
}
