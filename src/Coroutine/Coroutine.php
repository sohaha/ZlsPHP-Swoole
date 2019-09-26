<?php
declare(strict_types=1);

namespace Zls\Swoole\Coroutine;

use Z;
use Zls\Swoole\Utils;

abstract class Coroutine
{
    use Utils;

    abstract public function __construct(
        $timeout,
        $sum
    );

    abstract public function run($name, \Closure $func);

    abstract public function data();

    abstract public function defer(\Closure $func);

    abstract public function go(\Closure $func);

    public function sleep($time): void
    {
        sleep($time);
    }

    public static function wait(Coroutine $task)
    {
        return Z::arrayGet($task->data(), 'task.data');
    }

    public static function sync(\Closure $func)
    {
        $task = new static(-1, 1);
        $task->run("task", $func);

        return $task;
    }
}
