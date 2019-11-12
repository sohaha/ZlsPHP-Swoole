<?php
declare(strict_types=1);

namespace Zls\Swoole\Coroutine;

use Swoole\Coroutine as SwooleCo;
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

    abstract public function sleep($time): void;

    abstract public static function wait(Coroutine $task);

    abstract public static function sync(\Closure $func);

    abstract public static function id(): int;

    abstract public static function inCoroutine(): bool;
}
