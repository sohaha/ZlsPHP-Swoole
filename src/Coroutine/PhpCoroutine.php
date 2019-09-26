<?php
declare (strict_types=1);

namespace Zls\Swoole\Coroutine;

use Swoole\Coroutine\Channel;
use Z;

class PhpCoroutine extends Coroutine
{
    /** @var Channel $chan */
    private $chan;
    private $sum;
    private $outtime;
    private $data;

    public function __construct($timeout, $sum)
    {
    }

    public function run($name, \Closure $func): void
    {
        $this->data[$name] = ['data' => $func()];
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
}
