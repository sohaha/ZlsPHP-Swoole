<?php
declare (strict_types=1);

namespace Zls\Swoole\Coroutine;

use Swoole\Coroutine\Channel;
use Z;

class PhpCoroutine extends Coroutine
{
    /** @var Channel $chan */
    private $chan;
    private $sum = 0;
    private $outtime;
    private $data;

    public function __construct($timeout, $sum)
    {

    }

    public function run($name, \Closure $func): void
    {
        try {
            $data = ['data' => $func(), 'err' => null];
        } catch (\Zls_Exception_Exit $e) {
            $data = ['data' => $e->getMessage(), 'err' => null];
        } catch (\Error | \Exception $e) {
            $data = ['data' => null, 'err' => $e->getMessage()];
        }
        if ($name) {
            $this->data[$name] = $data;
        } else {
            $this->data[$this->sum] = $data;
        }
        ++$this->sum;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function defer(\Closure $func)
    {
        z::defer($func);
    }

    public function go(\Closure $func)
    {
        $func();
    }

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

    public static function id(): int
    {
        return 0;//getmypid();
    }

    public static function inCoroutine(): bool
    {
        return false;
    }
}
